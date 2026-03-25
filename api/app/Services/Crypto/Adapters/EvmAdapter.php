<?php

namespace App\Services\Crypto\Adapters;

use App\Services\Crypto\DTO\ChainTransaction;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use Elliptic\EC;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use kornrunner\Keccak;

class EvmAdapter implements ChainAdapterInterface
{
    private const CONFIRMATION_THRESHOLDS = [
        'ethereum' => 12,
        'bsc'      => 15,
    ];

    public function __construct(
        private readonly string $configKey,
    ) {}

    // ─── Interface Methods ──────────────────────────────────────────────

    public function fetchIncomingTransactions(string $address, ?string $sinceTimestamp = null): Collection
    {
        try {
            $params = [
                'module'          => 'account',
                'action'          => 'tokentx',
                'contractaddress' => $this->config('usdt_contract'),
                'address'         => $address,
                'sort'            => 'desc',
                'apikey'          => $this->config('explorer_api_key'),
            ];

            if ($sinceTimestamp) {
                // Etherscan expects a block number for startblock, but we can use
                // timestamp-based filtering after fetching. Just fetch recent txs.
            }

            $response = Http::timeout(15)
                ->get($this->config('explorer_url') . '/api', $params);

            if (! $response->successful()) {
                Log::warning("EvmAdapter[{$this->configKey}]: Explorer API request failed", [
                    'address' => $address,
                    'status'  => $response->status(),
                ]);

                return collect();
            }

            $results = $response->json('result', []);

            if (! is_array($results)) {
                Log::warning("EvmAdapter[{$this->configKey}]: Unexpected explorer response", [
                    'address' => $address,
                    'result'  => $results,
                ]);

                return collect();
            }

            $decimals = $this->config('token_decimals');
            $lowerAddress = strtolower($address);

            return collect($results)
                ->filter(function ($tx) use ($lowerAddress, $sinceTimestamp) {
                    // Only incoming transactions
                    if (strtolower($tx['to'] ?? '') !== $lowerAddress) {
                        return false;
                    }
                    // Filter by timestamp if provided (sinceTimestamp is in milliseconds)
                    if ($sinceTimestamp) {
                        $txTimestampMs = ((int) ($tx['timeStamp'] ?? 0)) * 1000;

                        return $txTimestampMs >= (int) $sinceTimestamp;
                    }

                    return true;
                })
                ->map(function ($tx) use ($decimals) {
                    $rawAmount = $tx['value'] ?? '0';
                    $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 6);

                    return new ChainTransaction(
                        txHash: $tx['hash'],
                        from: $tx['from'],
                        to: $tx['to'],
                        amount: $amount,
                        timestamp: ((int) ($tx['timeStamp'] ?? 0)) * 1000,
                        confirmations: (int) ($tx['confirmations'] ?? 0),
                    );
                });
        } catch (\Exception $e) {
            Log::error("EvmAdapter[{$this->configKey}]: fetchIncomingTransactions exception", [
                'address'   => $address,
                'exception' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    public function sendTransaction(
        string $fromAddress,
        string $toAddress,
        string $amount,
        string $privateKey
    ): ChainTransaction {
        $decimals = $this->config('token_decimals');
        $rawAmount = bcmul($amount, bcpow('10', (string) $decimals), 0);

        // Build ERC-20 transfer data
        $data = $this->encodeErc20Transfer($toAddress, $rawAmount);

        // Get gas price and validate
        $gasPrice = $this->getGasPrice();
        $maxGasPrice = $this->config('max_gas_price');
        if (bccomp($gasPrice, $maxGasPrice) > 0) {
            throw new TransactionBroadcastException(
                "Gas price {$gasPrice} exceeds maximum {$maxGasPrice}"
            );
        }

        // Get nonce
        $nonce = $this->getTransactionCount($fromAddress);

        // Build, sign, and send
        $gasLimit = $this->config('gas_limit_token_transfer');
        $contractAddress = $this->config('usdt_contract');

        $txHash = $this->signAndSendTransaction(
            nonce: $nonce,
            gasPrice: $gasPrice,
            gasLimit: $gasLimit,
            to: $contractAddress,
            value: '0',
            data: $data,
            privateKey: $privateKey,
        );

        return new ChainTransaction(
            txHash: $txHash,
            from: $fromAddress,
            to: $toAddress,
            amount: $amount,
            timestamp: (int) (microtime(true) * 1000),
            confirmations: 0,
        );
    }

    public function getNativeBalance(string $address): string
    {
        try {
            $result = $this->rpcCall('eth_getBalance', [$address, 'latest']);
            $wei = gmp_strval(gmp_init($result, 16));

            return bcdiv($wei, bcpow('10', '18'), 6);
        } catch (\Exception $e) {
            Log::error("EvmAdapter[{$this->configKey}]: getNativeBalance failed", [
                'address'   => $address,
                'exception' => $e->getMessage(),
            ]);

            return '0';
        }
    }

    public function getTokenBalance(string $address): string
    {
        try {
            // balanceOf(address) selector = 0x70a08231
            $addressParam = str_pad(substr(strtolower($address), 2), 64, '0', STR_PAD_LEFT);
            $callData = '0x70a08231' . $addressParam;

            $result = $this->rpcCall('eth_call', [
                ['to' => $this->config('usdt_contract'), 'data' => $callData],
                'latest',
            ]);

            if ($result === '0x' || $result === '0x0') {
                return '0';
            }

            $rawBalance = gmp_strval(gmp_init($result, 16));
            $decimals = $this->config('token_decimals');

            return bcdiv($rawBalance, bcpow('10', (string) $decimals), 6);
        } catch (\Exception $e) {
            Log::error("EvmAdapter[{$this->configKey}]: getTokenBalance failed", [
                'address'   => $address,
                'exception' => $e->getMessage(),
            ]);

            return '0';
        }
    }

    public function getTransactionInfo(string $txHash): ?array
    {
        try {
            $receipt = $this->rpcCall('eth_getTransactionReceipt', [$txHash]);

            if ($receipt === null) {
                return null;
            }

            $status = $receipt['status'] ?? '0x0';
            $success = $status === '0x1';

            // Calculate fee: gasUsed * effectiveGasPrice
            $gasUsed = gmp_strval(gmp_init($receipt['gasUsed'] ?? '0x0', 16));
            $effectiveGasPrice = gmp_strval(gmp_init($receipt['effectiveGasPrice'] ?? '0x0', 16));
            $feeWei = bcmul($gasUsed, $effectiveGasPrice, 0);
            $fee = bcdiv($feeWei, bcpow('10', '18'), 18);

            // Determine confirmation count
            $txBlockHex = $receipt['blockNumber'] ?? null;
            $confirmed = false;
            if ($txBlockHex) {
                $txBlock = gmp_intval(gmp_init($txBlockHex, 16));
                $currentBlockHex = $this->rpcCall('eth_blockNumber', []);
                $currentBlock = gmp_intval(gmp_init($currentBlockHex, 16));
                $confirmations = $currentBlock - $txBlock;
                $threshold = self::CONFIRMATION_THRESHOLDS[$this->configKey] ?? 12;
                $confirmed = $confirmations >= $threshold;
            }

            return [
                'confirmed' => $confirmed,
                'success'   => $success,
                'fee'       => $fee,
            ];
        } catch (\Exception $e) {
            Log::error("EvmAdapter[{$this->configKey}]: getTransactionInfo failed", [
                'txHash'    => $txHash,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    public function fetchTransactionHistory(
        string $address,
        int $limit = 200,
        ?string $fingerprint = null,
        ?string $minTimestamp = null,
    ): array {
        try {
            $params = [
                'module'          => 'account',
                'action'          => 'tokentx',
                'contractaddress' => $this->config('usdt_contract'),
                'address'         => $address,
                'sort'            => 'desc',
                'offset'          => min($limit, 200),
                'apikey'          => $this->config('explorer_api_key'),
            ];

            // Etherscan uses page-based pagination; fingerprint = page number
            $params['page'] = $fingerprint ? (int) $fingerprint : 1;

            if ($minTimestamp) {
                // minTimestamp is in milliseconds; Etherscan startblock is impractical,
                // so we filter post-fetch
            }

            $response = Http::timeout(15)
                ->get($this->config('explorer_url') . '/api', $params);

            if (! $response->successful()) {
                Log::warning("EvmAdapter[{$this->configKey}]: fetchTransactionHistory API failed", [
                    'address' => $address,
                    'status'  => $response->status(),
                ]);

                return ['data' => collect(), 'fingerprint' => null];
            }

            $results = $response->json('result', []);

            if (! is_array($results)) {
                return ['data' => collect(), 'fingerprint' => null];
            }

            $decimals = $this->config('token_decimals');

            $transactions = collect($results)
                ->filter(function ($tx) use ($minTimestamp) {
                    if ($minTimestamp) {
                        $txTimestampMs = ((int) ($tx['timeStamp'] ?? 0)) * 1000;

                        return $txTimestampMs >= (int) $minTimestamp;
                    }

                    return true;
                })
                ->map(function ($tx) use ($decimals) {
                    $rawAmount = $tx['value'] ?? '0';
                    $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 6);

                    return [
                        'tx_hash'         => $tx['hash'],
                        'from'            => $tx['from'],
                        'to'              => $tx['to'],
                        'amount'          => $amount,
                        'block_timestamp' => ((int) ($tx['timeStamp'] ?? 0)) * 1000,
                        'block_number'    => (int) ($tx['blockNumber'] ?? 0),
                        'type'            => 'Transfer',
                        'raw'             => $tx,
                    ];
                });

            // Next page fingerprint — if we got a full page, there may be more
            $currentPage = $params['page'];
            $nextFingerprint = count($results) >= min($limit, 200)
                ? (string) ($currentPage + 1)
                : null;

            return ['data' => $transactions, 'fingerprint' => $nextFingerprint];
        } catch (\Exception $e) {
            Log::error("EvmAdapter[{$this->configKey}]: fetchTransactionHistory exception", [
                'address'   => $address,
                'exception' => $e->getMessage(),
            ]);

            return ['data' => collect(), 'fingerprint' => null];
        }
    }

    public function sendNativeToken(
        string $fromAddress,
        string $toAddress,
        string $amount,
        string $privateKey
    ): string {
        // Convert amount to wei
        $wei = bcmul($amount, bcpow('10', '18'), 0);

        // Get gas price and validate
        $gasPrice = $this->getGasPrice();
        $maxGasPrice = $this->config('max_gas_price');
        if (bccomp($gasPrice, $maxGasPrice) > 0) {
            throw new TransactionBroadcastException(
                "Gas price {$gasPrice} exceeds maximum {$maxGasPrice}"
            );
        }

        $nonce = $this->getTransactionCount($fromAddress);
        $gasLimit = $this->config('gas_limit_native_transfer');

        return $this->signAndSendTransaction(
            nonce: $nonce,
            gasPrice: $gasPrice,
            gasLimit: $gasLimit,
            to: $toAddress,
            value: $wei,
            data: '',
            privateKey: $privateKey,
        );
    }

    public function getAccountResources(string $address): ?array
    {
        return null;
    }

    // ─── RPC Helpers ────────────────────────────────────────────────────

    /**
     * Make a JSON-RPC call to the EVM node.
     */
    private function rpcCall(string $method, array $params = []): mixed
    {
        $url = rtrim($this->config('rpc_url'), '/') . '/' . $this->config('rpc_api_key');

        $response = Http::timeout(15)->post($url, [
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => $method,
            'params'  => $params,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException(
                "RPC request failed [{$this->configKey}]: HTTP {$response->status()}"
            );
        }

        $json = $response->json();

        if (isset($json['error'])) {
            throw new \RuntimeException(
                "RPC error [{$this->configKey}]: {$json['error']['message']} (code: {$json['error']['code']})"
            );
        }

        return $json['result'];
    }

    private function config(string $key): mixed
    {
        return config("services.{$this->configKey}.{$key}");
    }

    // ─── Gas & Nonce ────────────────────────────────────────────────────

    private function getGasPrice(): string
    {
        $hex = $this->rpcCall('eth_gasPrice');

        return gmp_strval(gmp_init($hex, 16));
    }

    private function getTransactionCount(string $address): string
    {
        $hex = $this->rpcCall('eth_getTransactionCount', [$address, 'pending']);

        return gmp_strval(gmp_init($hex, 16));
    }

    // ─── ERC-20 ABI ─────────────────────────────────────────────────────

    /**
     * Encode ERC-20 transfer(address,uint256) call data.
     */
    private function encodeErc20Transfer(string $toAddress, string $rawAmount): string
    {
        // Function selector: keccak256("transfer(address,uint256)") first 4 bytes = a9059cbb
        $selector = 'a9059cbb';

        // Address: remove 0x prefix, left-pad to 32 bytes
        $addressParam = str_pad(substr(strtolower($toAddress), 2), 64, '0', STR_PAD_LEFT);

        // Amount: convert to hex, left-pad to 32 bytes
        $amountHex = gmp_strval(gmp_init($rawAmount), 16);
        $amountParam = str_pad($amountHex, 64, '0', STR_PAD_LEFT);

        return '0x' . $selector . $addressParam . $amountParam;
    }

    // ─── Transaction Signing (EIP-155) ──────────────────────────────────

    /**
     * Sign a transaction and broadcast it via eth_sendRawTransaction.
     *
     * @return string Transaction hash
     */
    private function signAndSendTransaction(
        string $nonce,
        string $gasPrice,
        int $gasLimit,
        string $to,
        string $value,
        string $data,
        string $privateKey,
    ): string {
        $chainId = (int) $this->config('chain_id');

        // Prepare hex fields
        $nonceHex = $this->intToHex($nonce);
        $gasPriceHex = $this->intToHex($gasPrice);
        $gasLimitHex = $this->intToHex((string) $gasLimit);
        $valueHex = $this->intToHex($value);
        $chainIdHex = $this->intToHex((string) $chainId);

        // Address is raw 20 bytes — preserve leading zeros
        $toHex = strtolower($to);
        if (str_starts_with($toHex, '0x')) {
            $toHex = substr($toHex, 2);
        }

        // Data is raw bytes — preserve leading zeros
        $dataHex = '';
        if ($data !== '') {
            $dataHex = str_starts_with($data, '0x') ? substr($data, 2) : $data;
        }

        // 1. RLP encode unsigned tx: [nonce, gasPrice, gasLimit, to, value, data, chainId, 0, 0]
        //    Integer fields use intToHex (leading zeros stripped).
        //    to/data are raw byte arrays (leading zeros preserved).
        $unsignedRlp = $this->rlpEncodeList([
            $this->rlpEncodeInteger($nonceHex),
            $this->rlpEncodeInteger($gasPriceHex),
            $this->rlpEncodeInteger($gasLimitHex),
            $this->rlpEncodeBytes($toHex),
            $this->rlpEncodeInteger($valueHex),
            $this->rlpEncodeBytes($dataHex),
            $this->rlpEncodeInteger($chainIdHex),
            $this->rlpEncodeInteger(''),  // 0
            $this->rlpEncodeInteger(''),  // 0
        ]);

        // 2. Keccak-256 hash
        $hash = Keccak::hash(hex2bin($unsignedRlp), 256);

        // 3. Sign with secp256k1
        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKey, 'hex');
        $signature = $key->sign($hash, ['canonical' => true]);

        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);

        // 4. EIP-155 v = chainId * 2 + 35 + recoveryParam
        $v = $chainId * 2 + 35 + $signature->recoveryParam;
        $vHex = $this->intToHex((string) $v);

        // 5. RLP encode signed tx: [nonce, gasPrice, gasLimit, to, value, data, v, r, s]
        //    r and s are big integers — strip leading zeros per RLP convention.
        $signedRlp = $this->rlpEncodeList([
            $this->rlpEncodeInteger($nonceHex),
            $this->rlpEncodeInteger($gasPriceHex),
            $this->rlpEncodeInteger($gasLimitHex),
            $this->rlpEncodeBytes($toHex),
            $this->rlpEncodeInteger($valueHex),
            $this->rlpEncodeBytes($dataHex),
            $this->rlpEncodeInteger($vHex),
            $this->rlpEncodeInteger($r),
            $this->rlpEncodeInteger($s),
        ]);

        $rawTx = '0x' . $signedRlp;

        // 6. Broadcast
        $txHash = $this->rpcCall('eth_sendRawTransaction', [$rawTx]);

        Log::info("EvmAdapter[{$this->configKey}]: Transaction broadcast", [
            'txHash' => $txHash,
            'to'     => $to,
        ]);

        return $txHash;
    }

    // ─── RLP Encoding ───────────────────────────────────────────────────

    /**
     * RLP-encode a list from pre-encoded items. Returns hex string (no 0x prefix).
     *
     * @param string[] $encodedItems Already RLP-encoded hex items.
     */
    private function rlpEncodeList(array $encodedItems): string
    {
        $payload = implode('', $encodedItems);
        $payloadLen = strlen($payload) / 2; // byte length

        if ($payloadLen < 56) {
            return dechex(0xc0 + $payloadLen) . $payload;
        }

        $lenHex = $this->intToHex((string) $payloadLen);
        $lenBytes = strlen($lenHex) / 2;

        return dechex(0xf7 + $lenBytes) . $lenHex . $payload;
    }

    /**
     * RLP-encode an integer value (hex string, leading zeros stripped).
     * Empty string represents zero.
     */
    private function rlpEncodeInteger(string $hexStr): string
    {
        // Strip leading zeros — integers use minimal encoding
        $hexStr = ltrim($hexStr, '0');

        if ($hexStr === '') {
            // Zero → single byte 0x80 (empty string encoding)
            return '80';
        }

        // Ensure even length
        if (strlen($hexStr) % 2 !== 0) {
            $hexStr = '0' . $hexStr;
        }

        return $this->rlpEncodeRaw($hexStr);
    }

    /**
     * RLP-encode raw byte data (hex string, preserves leading zeros).
     * Empty string encodes as empty byte string (0x80).
     */
    private function rlpEncodeBytes(string $hexStr): string
    {
        if ($hexStr === '') {
            return '80';
        }

        // Ensure even length
        if (strlen($hexStr) % 2 !== 0) {
            $hexStr = '0' . $hexStr;
        }

        return $this->rlpEncodeRaw($hexStr);
    }

    /**
     * RLP-encode a hex string (already properly formatted with even length).
     */
    private function rlpEncodeRaw(string $hexStr): string
    {
        $byteLen = strlen($hexStr) / 2;

        if ($byteLen === 1 && hexdec($hexStr) < 0x80) {
            // Single byte < 0x80 → itself
            return $hexStr;
        }

        if ($byteLen <= 55) {
            return dechex(0x80 + $byteLen) . $hexStr;
        }

        $lenHex = $this->intToHex((string) $byteLen);
        $lenOfLen = strlen($lenHex) / 2;

        return dechex(0xb7 + $lenOfLen) . $lenHex . $hexStr;
    }

    /**
     * Convert a decimal string to minimal hex representation (no leading zeros, no 0x prefix).
     * Returns empty string for "0".
     */
    private function intToHex(string $decimal): string
    {
        if ($decimal === '0' || $decimal === '') {
            return '';
        }

        $hex = gmp_strval(gmp_init($decimal, 10), 16);

        // Ensure even length
        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        return $hex;
    }
}
