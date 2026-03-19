<?php

namespace App\Services\Crypto\Adapters;

use App\Services\Crypto\DTO\ChainTransaction;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use Elliptic\EC;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class Trc20Adapter implements ChainAdapterInterface
{
    private const MAINNET_USDT_CONTRACT = 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t';
    private const FEE_LIMIT = 100000000; // 100 TRX

    public function fetchIncomingTransactions(string $address, ?string $sinceTimestamp = null): Collection
    {
        try {
            $params = [
                'only_to' => 'true',
                'contract_address' => $this->getUsdtContract(),
                'limit' => 20,
                'order_by' => 'block_timestamp,desc',
            ];

            if ($sinceTimestamp) {
                $params['min_timestamp'] = $sinceTimestamp;
            }

            $response = $this->buildHttpClient()
                ->get($this->getBaseUrl() . "/v1/accounts/{$address}/transactions/trc20", $params);

            if (!$response->successful()) {
                Log::warning('Trc20Adapter: TronGrid API 請求失敗', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                return collect();
            }

            $data = $response->json('data', []);

            return collect($data)
                ->filter(fn ($tx) => ($tx['token_info']['address'] ?? '') === $this->getUsdtContract())
                ->map(function ($tx) {
                    $decimals = (int) ($tx['token_info']['decimals'] ?? 6);
                    $rawAmount = $tx['value'] ?? '0';
                    $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 6);

                    return new ChainTransaction(
                        txHash: $tx['transaction_id'],
                        from: $tx['from'],
                        to: $tx['to'],
                        amount: $amount,
                        timestamp: (int) ($tx['block_timestamp'] ?? 0),
                        confirmations: 1, // TronGrid 回傳的交易已確認
                    );
                });
        } catch (\Exception $e) {
            Log::error('Trc20Adapter: 發生例外', [
                'address' => $address,
                'exception' => $e->getMessage(),
            ]);
            return collect();
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
                'contract_address' => $this->getUsdtContract(),
                'limit' => min($limit, 200),
                'order_by' => 'block_timestamp,desc',
            ];

            if ($fingerprint) {
                $params['fingerprint'] = $fingerprint;
            }

            if ($minTimestamp) {
                $params['min_timestamp'] = $minTimestamp;
            }

            $response = $this->buildHttpClient()
                ->get($this->getBaseUrl() . "/v1/accounts/{$address}/transactions/trc20", $params);

            if (!$response->successful()) {
                Log::warning('Trc20Adapter: fetchTransactionHistory API 請求失敗', [
                    'address' => $address,
                    'status' => $response->status(),
                ]);
                return ['data' => collect(), 'fingerprint' => null];
            }

            $json = $response->json();
            $data = $json['data'] ?? [];
            $nextFingerprint = $json['meta']['fingerprint'] ?? null;

            $transactions = collect($data)
                ->filter(fn ($tx) => ($tx['token_info']['address'] ?? '') === $this->getUsdtContract())
                ->map(function ($tx) {
                    $decimals = (int) ($tx['token_info']['decimals'] ?? 6);
                    $rawAmount = $tx['value'] ?? '0';
                    $amount = bcdiv($rawAmount, bcpow('10', (string) $decimals), 6);

                    return [
                        'tx_hash' => $tx['transaction_id'],
                        'from' => $tx['from'],
                        'to' => $tx['to'],
                        'amount' => $amount,
                        'block_timestamp' => (int) ($tx['block_timestamp'] ?? 0),
                        'block_number' => (int) ($tx['block_number'] ?? 0),
                        'type' => $tx['type'] ?? 'Transfer',
                        'raw' => $tx,
                    ];
                });

            return ['data' => $transactions, 'fingerprint' => $nextFingerprint];
        } catch (\Exception $e) {
            Log::error('Trc20Adapter: fetchTransactionHistory 發生例外', [
                'address' => $address,
                'exception' => $e->getMessage(),
            ]);
            return ['data' => collect(), 'fingerprint' => null];
        }
    }

    public function sendTransaction(
        string $fromAddress,
        string $toAddress,
        string $amount,
        string $privateKey
    ): ChainTransaction {
        $rawAmount = bcmul($amount, bcpow('10', '6'), 0);

        // 1. Create unsigned transaction
        $unsignedTx = $this->createTriggerSmartContract($fromAddress, $toAddress, $rawAmount);

        // 2. Sign
        $signedTx = $this->signTransaction($unsignedTx, $privateKey);

        // 3. Broadcast
        $txHash = $this->broadcastTransaction($signedTx);

        return new ChainTransaction(
            txHash: $txHash,
            from: $fromAddress,
            to: $toAddress,
            amount: $amount,
            timestamp: (int) (microtime(true) * 1000),
            confirmations: 0,
        );
    }

    private function createTriggerSmartContract(string $fromAddress, string $toAddress, string $rawAmount): array
    {
        $fromHex = $this->base58ToHex($fromAddress);
        $toHex = $this->base58ToHex($toAddress);

        // ABI encode: transfer(address,uint256)
        // address param: remove '41' prefix, pad to 32 bytes
        $addressParam = str_pad(substr($toHex, 2), 64, '0', STR_PAD_LEFT);
        // uint256 param: convert amount to hex, pad to 32 bytes
        $amountParam = str_pad(gmp_strval(gmp_init($rawAmount), 16), 64, '0', STR_PAD_LEFT);
        $parameter = $addressParam . $amountParam;

        $response = $this->buildHttpClient()->post($this->getBaseUrl() . '/wallet/triggersmartcontract', [
            'owner_address' => $fromHex,
            'contract_address' => $this->base58ToHex($this->getUsdtContract()),
            'function_selector' => 'transfer(address,uint256)',
            'parameter' => $parameter,
            'fee_limit' => (int) config('services.trongrid.fee_limit', self::FEE_LIMIT),
            'visible' => false,
        ]);

        $data = $response->json();

        if (!$response->successful() || !isset($data['transaction'])) {
            $errorMsg = $data['result']['message'] ?? 'Unknown error creating transaction';
            if (is_string($errorMsg) && ctype_xdigit($errorMsg)) {
                $errorMsg = hex2bin($errorMsg);
            }
            throw new TransactionBroadcastException("Failed to create transaction: {$errorMsg}");
        }

        return $data['transaction'];
    }

    private function signTransaction(array $transaction, string $privateKey): array
    {
        $txID = $transaction['txID'];

        $ec = new EC('secp256k1');
        $key = $ec->keyFromPrivate($privateKey, 'hex');
        $signature = $key->sign($txID, ['canonical' => true]);

        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = str_pad(dechex($signature->recoveryParam), 2, '0', STR_PAD_LEFT);

        $transaction['signature'] = [$r . $s . $v];

        return $transaction;
    }

    private function broadcastTransaction(array $signedTransaction): string
    {
        $response = $this->buildHttpClient()->post($this->getBaseUrl() . '/wallet/broadcasttransaction', $signedTransaction);

        $data = $response->json();

        if (!$response->successful() || !($data['result'] ?? false)) {
            $errorMsg = $data['message'] ?? ($data['result']['message'] ?? 'Unknown broadcast error');
            if (is_string($errorMsg) && ctype_xdigit($errorMsg)) {
                $errorMsg = hex2bin($errorMsg);
            }
            throw new TransactionBroadcastException("Broadcast failed: {$errorMsg}");
        }

        return $signedTransaction['txID'];
    }

    public function getNativeBalance(string $address): string
    {
        $response = $this->buildHttpClient()
            ->post($this->getBaseUrl() . '/wallet/getaccount', [
                'address' => $this->base58ToHex($address),
                'visible' => false,
            ]);

        if (!$response->successful()) {
            return '0';
        }

        $balance = $response->json('balance', 0);

        return bcdiv((string) $balance, '1000000', 6);
    }

    public function getTokenBalance(string $address): string
    {
        $addressParam = str_pad(substr($this->base58ToHex($address), 2), 64, '0', STR_PAD_LEFT);

        $response = $this->buildHttpClient()
            ->post($this->getBaseUrl() . '/wallet/triggerconstantcontract', [
                'owner_address' => $this->base58ToHex($address),
                'contract_address' => $this->base58ToHex($this->getUsdtContract()),
                'function_selector' => 'balanceOf(address)',
                'parameter' => $addressParam,
                'visible' => false,
            ]);

        if (!$response->successful()) {
            return '0';
        }

        $result = $response->json('constant_result.0', '0');
        if ($result === '0' || empty($result)) {
            return '0';
        }
        $rawBalance = gmp_strval(gmp_init($result, 16));

        return bcdiv($rawBalance, bcpow('10', '6'), 6);
    }

    public function getTransactionInfo(string $txHash): ?array
    {
        $response = $this->buildHttpClient()
            ->post($this->getBaseUrl() . '/walletsolidity/gettransactioninfobyid', [
                'value' => $txHash,
            ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json();

        // Empty response means transaction not yet confirmed
        if (empty($data) || !isset($data['id'])) {
            return null;
        }

        return [
            'confirmed' => true,
            'success'   => ($data['receipt']['result'] ?? '') === 'SUCCESS',
            'fee'       => bcdiv((string) ($data['fee'] ?? 0), '1000000', 6),
        ];
    }

    public function sendNativeToken(
        string $fromAddress,
        string $toAddress,
        string $amount,
        string $privateKey
    ): string {
        $rawAmount = (int) bcmul($amount, '1000000', 0);

        $fromHex = $this->base58ToHex($fromAddress);
        $toHex = $this->base58ToHex($toAddress);

        // 建立 TRX 轉帳交易
        $response = $this->buildHttpClient()->post($this->getBaseUrl() . '/wallet/createtransaction', [
            'owner_address' => $fromHex,
            'to_address' => $toHex,
            'amount' => $rawAmount,
            'visible' => false,
        ]);

        $data = $response->json();
        if (!$response->successful() || !isset($data['txID'])) {
            throw new TransactionBroadcastException('建立 TRX 轉帳失敗: ' . json_encode($data));
        }

        // 簽名並廣播
        $signedTx = $this->signTransaction($data, $privateKey);
        return $this->broadcastTransaction($signedTx);
    }

    /**
     * 預估 USDT 轉帳所需的 TRX（基於 Energy 消耗和當前 Energy 價格）
     *
     * @return string 預估所需 TRX（6 位小數）
     */
    public function estimateTransferFee(string $fromAddress, string $toAddress, string $amount): string
    {
        $rawAmount = bcmul($amount, bcpow('10', '6'), 0);
        $fromHex = $this->base58ToHex($fromAddress);
        $toHex = $this->base58ToHex($toAddress);

        $addressParam = str_pad(substr($toHex, 2), 64, '0', STR_PAD_LEFT);
        $amountParam = str_pad(gmp_strval(gmp_init($rawAmount), 16), 64, '0', STR_PAD_LEFT);

        // 1. 模擬交易取得 Energy 消耗
        $response = $this->buildHttpClient()
            ->post($this->getBaseUrl() . '/wallet/triggerconstantcontract', [
                'owner_address' => $fromHex,
                'contract_address' => $this->base58ToHex($this->getUsdtContract()),
                'function_selector' => 'transfer(address,uint256)',
                'parameter' => $addressParam . $amountParam,
                'visible' => false,
            ]);

        $energyUsed = (int) ($response->json('energy_used', 0) ?: 32000); // fallback ~32k

        // 2. 取得帳號現有的 Energy
        $accountResponse = $this->buildHttpClient()
            ->post($this->getBaseUrl() . '/wallet/getaccountresource', [
                'address' => $fromHex,
                'visible' => false,
            ]);

        $accountData = $accountResponse->json();
        $energyAvailable = (int) ($accountData['EnergyLimit'] ?? 0) - (int) ($accountData['EnergyUsed'] ?? 0);
        $energyAvailable = max(0, $energyAvailable);

        // 需要用 TRX 補的 Energy
        $energyToBurn = max(0, $energyUsed - $energyAvailable);

        if ($energyToBurn <= 0) {
            return '0';
        }

        // 3. 取得當前 Energy 價格（sun per Energy）
        $priceResponse = $this->buildHttpClient()
            ->get($this->getBaseUrl() . '/wallet/getenergyprices');

        $pricesStr = $priceResponse->json('prices', '');
        $energyPrice = 420; // 預設 420 sun
        if ($pricesStr) {
            $parts = explode(',', $pricesStr);
            $lastPart = end($parts);
            $priceParts = explode(':', $lastPart);
            $energyPrice = (int) (end($priceParts) ?: 420);
        }

        // TRX = energyToBurn × energyPrice / 1_000_000
        $trxNeeded = bcdiv(bcmul((string) $energyToBurn, (string) $energyPrice, 0), '1000000', 6);

        return $trxNeeded;
    }

    private function getUsdtContract(): string
    {
        return config('services.trongrid.usdt_contract', self::MAINNET_USDT_CONTRACT);
    }

    private function getBaseUrl(): string
    {
        return config('services.trongrid.base_url', 'https://api.trongrid.io');
    }

    private function buildHttpClient(): PendingRequest
    {
        $client = Http::timeout(15);
        $apiKey = config('services.trongrid.api_key');
        if ($apiKey) {
            $client = $client->withHeaders(['TRON-PRO-API-KEY' => $apiKey]);
        }

        return $client;
    }

    /**
     * Convert TRON base58check address to hex format (41-prefixed).
     * Validates the double-SHA256 checksum to reject mistyped addresses.
     */
    private function base58ToHex(string $base58Address): string
    {
        $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $base = gmp_init(58);
        $num = gmp_init(0);

        for ($i = 0; $i < strlen($base58Address); $i++) {
            $num = gmp_mul($num, $base);
            $num = gmp_add($num, gmp_init(strpos($alphabet, $base58Address[$i])));
        }

        $hex = gmp_strval($num, 16);
        // Pad to 50 chars (25 bytes: 1 byte prefix + 20 bytes address + 4 bytes checksum)
        $hex = str_pad($hex, 50, '0', STR_PAD_LEFT);

        // Verify base58check checksum (last 4 bytes = first 4 bytes of double-SHA256 of payload)
        $payload = hex2bin(substr($hex, 0, 42)); // 21 bytes: prefix + address
        $checksum = substr($hex, 42, 8);         // 4 bytes checksum
        $expectedChecksum = substr(hash('sha256', hash('sha256', $payload, true)), 0, 8);

        if ($checksum !== $expectedChecksum) {
            throw new \InvalidArgumentException("Invalid TRON address checksum: {$base58Address}");
        }

        // Return first 42 chars (21 bytes: prefix + address, without checksum)
        return substr($hex, 0, 42);
    }
}
