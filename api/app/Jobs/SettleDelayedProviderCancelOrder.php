<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Transaction\TransactionStatusService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SettleDelayedProviderCancelOrder implements ShouldQueue
{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @var Transaction
     */
    public $transaction;

    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
        $this->queue = config('queue.queue-priority.high');
    }

    /**
     * Execute the job.
     *
     * @param  TransactionStatusService  $statusService
     * @return void
     */
    public function handle(TransactionStatusService $statusService)
    {
        $statusService->markAsRefunded(
          $this->transaction,
          $this->transaction->lockedBy,
          false,
          $this->transaction->status === Transaction::STATUS_PAYING_TIMED_OUT
        );
    }
}
