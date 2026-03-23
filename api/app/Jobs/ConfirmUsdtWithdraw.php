<?php

namespace App\Jobs;

use App\Models\ChainTransaction;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\UserChannelAccount;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\ChainAdapterFactory;
use App\Services\Transaction\TransactionStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConfirmUsdtWithdraw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 20;
    public int $backoff = 30;

    public function __construct(
        public readonly int $transactionId
    ) {}

    public function handle(TransactionStatusService $statusService): void
    {
        $transaction = Transaction::find($this->transactionId);
        if (!$transaction || empty($transaction->tx_hash)) {
            return;
        }

        // 已經成功的不再處理
        if ($transaction->isSuccessful()) {
            return;
        }

        $adapter = $this->resolveAdapter($transaction->chain_network ?? 'trc20');
        if (!$adapter) {
            return;
        }

        $info = $adapter->getTransactionInfo($transaction->tx_hash);

        if ($info === null) {
            Log::info('ConfirmUsdtWithdraw: 交易尚未確認', [
                'transaction_id' => $transaction->id,
                'tx_hash' => $transaction->tx_hash,
                'attempt' => $this->attempts(),
            ]);
            $this->release($this->backoff);
            return;
        }

        if ($info['confirmed'] && $info['success']) {
            $transaction->update(['chain_fee' => $info['fee']]);

            // 透過 markAsSuccess 走完整流程：帳號餘額扣減 + 帳變記錄 + 回調通知
            $statusService->markAsSuccess($transaction, operator: null, autoSuccess: true, shouldLock: false);

            // 同步出款帳號的鏈上餘額
            $this->syncAccountBalance($transaction, $adapter);

            // 內轉/批量轉帳：更新接收帳號的餘額和額度
            $this->updateReceiverAccount($transaction, $adapter);

            // 更新鏈上交易記錄的確認狀態
            $this->updateChainTransactionStatus($transaction, true);

            $this->log($transaction, "鏈上交易已確認成功 (tx_hash: {$transaction->tx_hash})", [
                'tx_hash' => $transaction->tx_hash,
                'fee' => $info['fee'],
            ]);
        } else {
            $transaction->update([
                'status' => Transaction::STATUS_FAILED,
            ]);

            // 更新鏈上交易記錄為失敗
            $this->updateChainTransactionStatus($transaction, false);

            $this->log($transaction, "鏈上交易失敗 (tx_hash: {$transaction->tx_hash})", [
                'tx_hash' => $transaction->tx_hash,
                'info' => $info,
            ], 'error');

            // 失敗時也通知商戶
            NotifyTransaction::dispatch($transaction);
        }
    }

    private function log(Transaction $transaction, string $message, array $context = [], string $level = 'info'): void
    {
        $logContext = array_merge(['transaction_id' => $transaction->id], $context);
        Log::$level("ConfirmUsdtWithdraw: {$message}", $logContext);

        TransactionNote::create([
            'transaction_id' => $transaction->id,
            'user_id' => 0,
            'note' => "[USDT確認] {$message}",
        ]);
    }

    private function updateChainTransactionStatus(Transaction $transaction, bool $success): void
    {
        try {
            ChainTransaction::where('matched_transaction_id', $transaction->id)
                ->update([
                    'confirmations' => 1,
                    'note' => $success ? null : 'FAILED ON-CHAIN (TRANSACTION REVERT)',
                ]);
        } catch (\Throwable $e) {
            Log::warning('ConfirmUsdtWithdraw: 更新鏈上交易狀態失敗', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function syncAccountBalance(Transaction $transaction, ChainAdapterInterface $adapter): void
    {
        $accountIds = array_filter([
            $transaction->to_channel_account_id,
            $transaction->from_channel_account_id,
        ]);

        foreach ($accountIds as $accountId) {
            try {
                $account = UserChannelAccount::find($accountId);
                if (!$account) {
                    continue;
                }

                $account->update([
                    'onchain_usdt_balance' => $adapter->getTokenBalance($account->account),
                    'onchain_native_balance' => $adapter->getNativeBalance($account->account),
                    'onchain_synced_at' => now(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('ConfirmUsdtWithdraw: 同步帳號餘額失敗', [
                    'transaction_id' => $transaction->id,
                    'account_id' => $accountId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 內轉/批量轉帳成功後：更新接收帳號的 balance 和同步鏈上餘額
     */
    private function updateReceiverAccount(Transaction $transaction, ChainAdapterInterface $adapter): void
    {
        if (!in_array($transaction->type, [Transaction::TYPE_INTERNAL_TRANSFER, Transaction::TYPE_NATIVE_TRANSFER])) {
            return;
        }

        $targetAddress = data_get($transaction->from_channel_account, 'bank_card_number');
        if (!$targetAddress) {
            return;
        }

        $receiverAccount = UserChannelAccount::where('account', $targetAddress)->first();
        if (!$receiverAccount) {
            return;
        }

        try {
            // 增加 balance（系統餘額）
            $receiverAccount->increment('balance', $transaction->amount);

            // 同步鏈上餘額
            $receiverAccount->update([
                'onchain_usdt_balance' => $adapter->getTokenBalance($receiverAccount->account),
                'onchain_native_balance' => $adapter->getNativeBalance($receiverAccount->account),
                'onchain_synced_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ConfirmUsdtWithdraw: 更新接收帳號餘額失敗', [
                'transaction_id' => $transaction->id,
                'receiver_account_id' => $receiverAccount->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveAdapter(string $chainNetwork): ?ChainAdapterInterface
    {
        return ChainAdapterFactory::make($chainNetwork);
    }
}
