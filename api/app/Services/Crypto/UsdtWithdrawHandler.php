<?php

namespace App\Services\Crypto;

use App\Models\ChainTransaction;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
use App\Jobs\ConfirmUsdtWithdraw;
use App\Services\Crypto\Exceptions\InsufficientBalanceException;
use App\Services\Crypto\Exceptions\TransactionBroadcastException;
use Illuminate\Support\Facades\Log;

class UsdtWithdrawHandler
{
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
        $minNativeBalance = match ($chainNetwork) {
            'trc20' => config('services.trongrid.min_trx_balance', '30'),
            'erc20' => config('services.ethereum.min_native_balance', '0.005'),
            'bep20' => config('services.bsc.min_native_balance', '0.005'),
            default => '0',
        };
        $gasTokenName = match ($chainNetwork) {
            'trc20' => 'TRX', 'erc20' => 'ETH', 'bep20' => 'BNB', default => 'Native',
        };
        $nativeBalance = $adapter->getNativeBalance($fromAddress);

        if (bccomp($nativeBalance, $minNativeBalance, 6) < 0) {
            $this->log($transaction, "{$gasTokenName} 餘額不足支付 Gas (餘額: {$nativeBalance}, 最低: {$minNativeBalance})", [
                'native_balance' => $nativeBalance,
                'min_required'   => $minNativeBalance,
                'gas_token'      => $gasTokenName,
            ], 'error');
            throw new InsufficientBalanceException(
                "{$gasTokenName} balance {$nativeBalance} below minimum {$minNativeBalance} for gas fees"
            );
        }

        $toAddress = data_get($transaction->from_channel_account, 'bank_card_number', '');
        $amount = $transaction->floating_amount ?? $transaction->amount;

        if (empty($toAddress)) {
            $this->log($transaction, '目標地址為空', level: 'error');
            return;
        }

        $privateKey = null;
        try {
            $privateKey = decrypt($encryptedKey);
            $chainTx = $adapter->sendTransaction($fromAddress, $toAddress, $amount, $privateKey);

            $transaction->update([
                'tx_hash' => $chainTx->txHash,
                'chain_network' => $chainNetwork,
            ]);

            // Dispatch delayed confirmation check
            ConfirmUsdtWithdraw::dispatch($transaction->id)->delay(now()->addSeconds(15));

            // 主動建立鏈上交易記錄並標記匹配，避免 sync→match 非同步流程誤匹配
            $this->createMatchedChainTransactions(
                $transaction, $chainTx->txHash, $account, $fromAddress, $toAddress, $amount
            );

            $this->log($transaction, "出款交易已廣播 (tx_hash: {$chainTx->txHash})", [
                'tx_hash' => $chainTx->txHash,
            ]);
        } catch (InsufficientBalanceException|TransactionBroadcastException $e) {
            $this->log($transaction, "鏈上出款失敗: {$e->getMessage()}", [
                'error' => $e->getMessage(),
            ], 'error');
            throw $e;
        } catch (\Throwable $e) {
            $this->log($transaction, "未預期錯誤: {$e->getMessage()}", [
                'error' => $e->getMessage(),
            ], 'error');
            throw $e;
        } finally {
            $privateKey = null;
        }
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
                    'block_timestamp' => now(),
                    'confirmations' => 0,
                    'source' => ChainTransaction::SOURCE_INTERNAL,
                    'match_status' => ChainTransaction::STATUS_MATCHED,
                    'matched_transaction_id' => $transaction->id,
                    'matched_at' => now(),
                ]
            );

            // IN record for receiver (only if address belongs to a platform account)
            $receiverAccount = UserChannelAccount::where('channel_code', Channel::CODE_USDT)
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
        return match ($chainNetwork) {
            'trc20' => app(Trc20Adapter::class),
            'erc20' => app('evm.adapter.erc20'),
            'bep20' => app('evm.adapter.bep20'),
            default => throw new \InvalidArgumentException("不支援的鏈網路: {$chainNetwork}"),
        };
    }
}
