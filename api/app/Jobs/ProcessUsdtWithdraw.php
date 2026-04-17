<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Services\Crypto\UsdtWithdrawHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUsdtWithdraw implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(
        public readonly int $transactionId
    ) {}

    public function handle(UsdtWithdrawHandler $handler): void
    {
        $transaction = Transaction::find($this->transactionId);
        if (!$transaction) {
            return;
        }

        $handler->handle($transaction);
    }

    /**
     * 所有重試都失敗後：
     * - 建立轉帳 → 標記為失敗
     * - 代付 → 保持 paying 狀態，等手動處理
     */
    public function failed(\Throwable $exception): void
    {
        try {
            $transaction = Transaction::find($this->transactionId);
            if (!$transaction) {
                return;
            }

            TransactionNote::create([
                'transaction_id' => $this->transactionId,
                'user_id' => 0,
                'note' => "[USDT出款] 重試全部失敗: {$exception->getMessage()}",
            ]);

            // 代付保持 paying 狀態，由人工介入處理
            if ($transaction->type !== Transaction::TYPE_INTERNAL_TRANSFER) {
                Log::warning("ProcessUsdtWithdraw: 代付重試全部失敗，保持 paying 等待手動處理", [
                    'transaction_id' => $this->transactionId,
                    'error' => $exception->getMessage(),
                ]);
                return;
            }

            // 建立轉帳 → 標記為失敗
            if ($transaction->status !== Transaction::STATUS_FAILED) {
                $transaction->update([
                    'status' => Transaction::STATUS_FAILED,
                ]);
                Log::error("ProcessUsdtWithdraw: 建立轉帳重試全部失敗，標記為失敗", [
                    'transaction_id' => $this->transactionId,
                    'error' => $exception->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error("ProcessUsdtWithdraw: failed() 處理異常", [
                'transaction_id' => $this->transactionId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
