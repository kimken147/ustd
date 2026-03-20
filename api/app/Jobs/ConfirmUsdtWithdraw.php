<?php

namespace App\Jobs;

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

            $this->log($transaction, "鏈上交易已確認成功 (tx_hash: {$transaction->tx_hash})", [
                'tx_hash' => $transaction->tx_hash,
                'fee' => $info['fee'],
            ]);
        } else {
            $transaction->update([
                'status' => Transaction::STATUS_FAILED,
            ]);

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

    private function resolveAdapter(string $chainNetwork): ?ChainAdapterInterface
    {
        return ChainAdapterFactory::make($chainNetwork);
    }
}
