<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Crypto\UsdtWithdrawHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
}
