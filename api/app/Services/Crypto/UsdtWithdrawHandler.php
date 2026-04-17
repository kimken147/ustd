<?php

namespace App\Services\Crypto;

use App\Models\ChainTransaction;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\ChainAdapterFactory;
use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Services\Crypto\EnergyRental\EnergyRentalProviderInterface;
use App\Jobs\ConfirmUsdtWithdraw;
use App\Services\Crypto\Exceptions\InsufficientBalanceException;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use Illuminate\Support\Facades\Log;

class UsdtWithdrawHandler
{
    public function __construct(
        private readonly EnergyRentalProviderInterface $energyProvider,
    ) {}

    public function handle(Transaction $transaction): void
    {
        // Idempotency: skip if already broadcast
        if (!empty($transaction->tx_hash)) {
            $this->log($transaction, '交易已有 tx_hash，跳過', [
                'tx_hash' => $transaction->tx_hash,
            ]);
            return;
        }

        $account = UserChannelAccount::find($transaction->to_channel_account_id);
        if (!$account) {
            $this->log($transaction, '找不到出款帳號', level: 'error');
            return;
        }

        $encryptedKey = data_get($account->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
        if (!$encryptedKey) {
            $this->log($transaction, '帳號未設定私鑰，跳過自動出款', [
                'account_id' => $account->id,
            ], 'warning');
            return;
        }

        $chainNetwork = data_get($account->detail, UserChannelAccount::DETAIL_KEY_CHAIN_NETWORK, 'trc20');
        $adapter = $this->resolveAdapter($chainNetwork);

        $fromAddress = $account->account;

        // Pre-flight: check native token balance for gas fees
        $gasTokenName = match ($chainNetwork) {
            'trc20' => 'TRX', 'erc20' => 'ETH', 'bep20' => 'BNB', default => 'Native',
        };

        $toAddress = data_get($transaction->from_channel_account, 'bank_card_number', '');
        $txAmount = $transaction->floating_amount ?? $transaction->amount;

        if (empty($toAddress)) {
            $this->log($transaction, '目標地址為空', level: 'error');
            return;
        }

        $nativeBalance = $adapter->getNativeBalance($fromAddress);

        // TRC-20: 頻寬檢查（放在租能量之前，避免頻寬不夠時浪費能量租賃費）
        if ($chainNetwork === 'trc20' && $adapter instanceof Trc20Adapter) {
            $resources = $adapter->getAccountResources($fromAddress);
            $bandwidthAvailable = $resources['bandwidth_available'] ?? 0;
            $minBandwidth = 350;
            if ($bandwidthAvailable < $minBandwidth && bccomp($nativeBalance, '1', 6) < 0) {
                $this->log($transaction, "頻寬不足且無 TRX 支付頻寬費 (出款地址: {$fromAddress}, 可用頻寬: {$bandwidthAvailable}, 需要: {$minBandwidth}, TRX: {$nativeBalance})", [
                    'from_address' => $fromAddress,
                    'bandwidth_available' => $bandwidthAvailable,
                    'min_bandwidth' => $minBandwidth,
                    'native_balance' => $nativeBalance,
                ], 'error');
                throw new InsufficientBalanceException(
                    "Bandwidth {$bandwidthAvailable} < {$minBandwidth} and TRX {$nativeBalance} insufficient (address: {$fromAddress})"
                );
            }
        }

        // 代付 + TRC-20 + 能量租賃可用 → 租賃能量
        // 失敗時 fallback：從母地址補充 TRX，用 TRX 燒能量出款
        $energyRented = false;
        if ($this->shouldRentEnergy($transaction, $chainNetwork)) {
            try {
                $this->delegateEnergy($transaction, $adapter, $fromAddress, $toAddress, $txAmount);
                $energyRented = true;
            } catch (\Throwable $e) {
                $this->log($transaction, "能量租賃失敗 (出款地址: {$fromAddress}): {$e->getMessage()}", [
                    'from_address' => $fromAddress,
                    'error' => $e->getMessage(),
                ], 'warning');

                // Fallback: 從母地址補充 TRX 給子地址，用 TRX 燒能量+頻寬
                $this->topUpTrxFromParent($transaction, $account, $adapter, $fromAddress, $toAddress, $txAmount);
                // 重新查詢餘額（已包含母地址補充的 TRX）
                $nativeBalance = $adapter->getNativeBalance($fromAddress);
            }
        }

        // 動態預估所需 Gas（TRC-20 用 Energy 預估，其他用固定最低值）
        $requiredGas = match ($chainNetwork) {
            'trc20' => $adapter instanceof Trc20Adapter
                ? $adapter->estimateTransferFee($fromAddress, $toAddress ?: $fromAddress, $txAmount)
                : config('services.trongrid.min_trx_balance', '30'),
            'erc20' => config('services.ethereum.min_native_balance', '0.005'),
            'bep20' => config('services.bsc.min_native_balance', '0.005'),
            default => '0',
        };
        // 加 buffer：已租賃能量且預估為 0 則不需 buffer，否則 TRC-20 多 5 TRX，其他多 10%
        $buffer = match (true) {
            $energyRented && bccomp($requiredGas, '0', 6) === 0 => '0',
            $chainNetwork === 'trc20' => '5',
            default => bcmul($requiredGas, '0.1', 6),
        };
        $requiredWithBuffer = bcadd($requiredGas, $buffer, 6);

        if (bccomp($nativeBalance, $requiredWithBuffer, 6) < 0) {
            $this->log($transaction, "{$gasTokenName} 餘額不足支付 Gas，請手動補充 (出款地址: {$fromAddress}, 餘額: {$nativeBalance}, 需要: {$requiredWithBuffer})", [
                'from_address'   => $fromAddress,
                'native_balance' => $nativeBalance,
                'required'       => $requiredWithBuffer,
                'gas_token'      => $gasTokenName,
            ], 'error');
            throw new InsufficientBalanceException(
                "{$gasTokenName} balance {$nativeBalance}, need {$requiredWithBuffer} for gas fees"
            );
        }

        $privateKey = null;
        try {
            $privateKey = decrypt($encryptedKey);
            $chainTx = $adapter->sendTransaction($fromAddress, $toAddress, $txAmount, $privateKey);

            $transaction->update([
                'tx_hash' => $chainTx->txHash,
                'chain_network' => $chainNetwork,
            ]);

            // Dispatch delayed confirmation check
            ConfirmUsdtWithdraw::dispatch($transaction->id)->delay(now()->addSeconds(15));

            // 主動建立鏈上交易記錄並標記匹配，避免 sync→match 非同步流程誤匹配
            $this->createMatchedChainTransactions(
                $transaction, $chainTx->txHash, $account, $fromAddress, $toAddress, $txAmount
            );

            $this->log($transaction, "出款交易已廣播 (出款地址: {$fromAddress}, tx_hash: {$chainTx->txHash})", [
                'from_address' => $fromAddress,
                'tx_hash' => $chainTx->txHash,
            ]);
        } catch (InsufficientBalanceException|TransactionBroadcastException $e) {
            $this->log($transaction, "鏈上出款失敗 (出款地址: {$fromAddress}): {$e->getMessage()}", [
                'from_address' => $fromAddress,
                'error' => $e->getMessage(),
            ], 'error');
            throw $e;
        } catch (\Throwable $e) {
            $this->log($transaction, "未預期錯誤 (出款地址: {$fromAddress}): {$e->getMessage()}", [
                'from_address' => $fromAddress,
                'error' => $e->getMessage(),
            ], 'error');
            throw $e;
        } finally {
            $privateKey = null;
        }
    }

    /**
     * 判斷是否應租賃能量
     */
    private function shouldRentEnergy(Transaction $transaction, string $chainNetwork): bool
    {
        return $chainNetwork === 'trc20'
            && $transaction->sub_type === Transaction::SUB_TYPE_AGENCY_WITHDRAW
            && $this->energyProvider->isAvailable();
    }

    /**
     * 租賃能量（失敗則中斷代付流程）
     */
    private function delegateEnergy(
        Transaction $transaction,
        ChainAdapterInterface $adapter,
        string $fromAddress,
        string $toAddress,
        string $txAmount,
    ): void {
        // 查詢收款地址的 USDT 餘額，決定所需能量
        $receiverUsdtBalance = $adapter->getTokenBalance($toAddress);
        $energyNeeded = bccomp($receiverUsdtBalance, '0', 6) > 0 ? 65000 : 131000;

        $result = $this->energyProvider->delegateEnergy($fromAddress, $energyNeeded);

        $this->log($transaction, "能量租賃成功 [{$this->energyProvider->name()}]: {$energyNeeded} energy, 花費 {$result['paid_trx']} TRX (出款地址: {$fromAddress}, order: {$result['order_id']})", [
            'energy_provider' => $this->energyProvider->name(),
            'energy_needed'   => $energyNeeded,
            'paid_trx'        => $result['paid_trx'],
            'order_id'        => $result['order_id'],
            'from_address'    => $fromAddress,
            'receiver_usdt'   => $receiverUsdtBalance,
        ]);

        // 等待能量委託到帳（Netts 通常 0.5-2 秒）
        sleep(3);
    }

    /**
     * 能量租賃失敗時的 fallback：從母地址補充 TRX 給子地址
     * 讓子地址用 TRX 燒能量+頻寬來完成出款
     */
    private function topUpTrxFromParent(
        Transaction $transaction,
        UserChannelAccount $account,
        ChainAdapterInterface $adapter,
        string $childAddress,
        string $toAddress,
        string $txAmount,
    ): void {
        $parentAccount = $account->parentAccount;
        if (!$parentAccount) {
            $this->log($transaction, "能量租賃失敗且無母地址可補充 TRX (出款地址: {$childAddress})", [
                'from_address' => $childAddress,
            ], 'error');
            throw new InsufficientBalanceException(
                "Energy rental failed and no parent account to top up TRX (address: {$childAddress})"
            );
        }

        $parentAddress = $parentAccount->account;
        $parentKey = data_get($parentAccount->detail, UserChannelAccount::DETAIL_KEY_ENCRYPTED_PRIVATE_KEY);
        if (!$parentKey) {
            $this->log($transaction, "母地址未設定私鑰，無法補充 TRX (母地址: {$parentAddress})", [
                'parent_address' => $parentAddress,
            ], 'error');
            throw new InsufficientBalanceException(
                "Parent account has no private key (parent: {$parentAddress})"
            );
        }

        // 預估所需 TRX（能量費 + 頻寬費 + buffer）
        $estimatedGas = ($adapter instanceof Trc20Adapter)
            ? $adapter->estimateTransferFee($childAddress, $toAddress ?: $childAddress, $txAmount)
            : '30';
        $trxNeeded = bcadd($estimatedGas, '5', 6); // +5 TRX buffer

        $parentBalance = $adapter->getNativeBalance($parentAddress);
        if (bccomp($parentBalance, bcadd($trxNeeded, '1', 6), 6) < 0) {
            $this->log($transaction, "母地址 TRX 不足，無法補充 (母地址: {$parentAddress}, 餘額: {$parentBalance}, 需要: {$trxNeeded})", [
                'parent_address' => $parentAddress,
                'parent_balance' => $parentBalance,
                'trx_needed' => $trxNeeded,
            ], 'error');
            throw new InsufficientBalanceException(
                "Parent TRX balance {$parentBalance} insufficient, need {$trxNeeded} (parent: {$parentAddress})"
            );
        }

        // 從母地址轉 TRX 到子地址
        $privateKey = decrypt($parentKey);
        try {
            $txHash = $adapter->sendNativeToken($parentAddress, $childAddress, $trxNeeded, $privateKey);
        } finally {
            $privateKey = null;
        }

        $this->log($transaction, "已從母地址補充 {$trxNeeded} TRX (母地址: {$parentAddress}, 出款地址: {$childAddress}, tx: {$txHash})");

        // 等待 TRX 轉帳確認
        sleep(5);
    }

    /**
     * 廣播成功後主動建立 ChainTransaction 記錄並標記為已匹配。
     * 出金方建立 OUT 記錄；若收款方也是平台帳號（內轉），建立 IN 記錄。
     * 這樣後續 ChainTransactionSyncService 同步時會 updateOrCreate 命中已有記錄，
     * 不會再觸發 ChainTransactionMatchService 的自動比對。
     */
    private function createMatchedChainTransactions(
        Transaction $transaction,
        string $txHash,
        UserChannelAccount $senderAccount,
        string $fromAddress,
        string $toAddress,
        string $amount,
    ): void {
        try {
            // OUT record for sender
            ChainTransaction::updateOrCreate(
                ['tx_hash' => $txHash, 'user_channel_account_id' => $senderAccount->id],
                [
                    'direction' => ChainTransaction::DIRECTION_OUT,
                    'from_address' => $fromAddress,
                    'to_address' => $toAddress,
                    'amount' => $amount,
                    'token_type' => ChainTransaction::TOKEN_TYPE_USDT,
                    'block_timestamp' => now(),
                    'confirmations' => 0,
                    'source' => ChainTransaction::SOURCE_INTERNAL,
                    'match_status' => ChainTransaction::STATUS_MATCHED,
                    'matched_transaction_id' => $transaction->id,
                    'matched_at' => now(),
                ]
            );

            // IN record for receiver (only if address belongs to a platform account)
            $receiverAccount = UserChannelAccount::whereIn('channel_code', Channel::USDT_CODES)
                ->where('account', $toAddress)
                ->whereNull('deleted_at')
                ->first();

            if ($receiverAccount) {
                ChainTransaction::updateOrCreate(
                    ['tx_hash' => $txHash, 'user_channel_account_id' => $receiverAccount->id],
                    [
                        'direction' => ChainTransaction::DIRECTION_IN,
                        'from_address' => $fromAddress,
                        'to_address' => $toAddress,
                        'amount' => $amount,
                        'token_type' => ChainTransaction::TOKEN_TYPE_USDT,
                        'block_timestamp' => now(),
                        'confirmations' => 0,
                        'source' => ChainTransaction::SOURCE_INTERNAL,
                        'match_status' => ChainTransaction::STATUS_MATCHED,
                        'matched_transaction_id' => $transaction->id,
                        'matched_at' => now(),
                    ]
                );
            }
        } catch (\Throwable $e) {
            // 不影響主流程，僅記錄錯誤
            Log::warning('UsdtWithdrawHandler: 建立匹配鏈上交易記錄失敗', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $txHash,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function log(Transaction $transaction, string $message, array $context = [], string $level = 'info'): void
    {
        $logContext = array_merge(['transaction_id' => $transaction->id], $context);
        Log::$level("UsdtWithdrawHandler: {$message}", $logContext);

        TransactionNote::create([
            'transaction_id' => $transaction->id,
            'user_id' => 0,
            'note' => "[USDT出款] {$message}",
        ]);
    }

    private function resolveAdapter(string $chainNetwork): ChainAdapterInterface
    {
        return ChainAdapterFactory::makeOrFail($chainNetwork);
    }
}
