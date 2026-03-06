<?php

namespace App\Jobs;

use App\Jobs\NotifyTransaction;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Services\Crypto\Adapters\ChainAdapterInterface;
use App\Services\Crypto\Adapters\Trc20Adapter;
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

    public function handle(): void
    {
        $transaction = Transaction::find($this->transactionId);
        if (!$transaction || empty($transaction->tx_hash)) {
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
            $transaction->update([
                'status' => Transaction::STATUS_SUCCESS,
            ]);

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
        }

        // Notify merchant of the final withdrawal result
        NotifyTransaction::dispatch($transaction);
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

    private function resolveAdapter(string $chainNetwork): ?ChainAdapterInterface
    {
        return match ($chainNetwork) {
            'trc20' => app(Trc20Adapter::class),
            default => null,
        };
    }
}
