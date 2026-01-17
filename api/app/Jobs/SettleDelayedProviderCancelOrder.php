<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Utils\TransactionUtil;
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
     * @param  TransactionUtil  $transactionUtil
     * @return void
     */
    public function handle(TransactionUtil $transactionUtil)
    {
        $transactionUtil->markAsRefunded(
          $this->transaction,
          $this->transaction->lockedBy,
          false,
          $this->transaction->status === Transaction::STATUS_PAYING_TIMED_OUT
        );
    }
}
