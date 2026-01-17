<?php

namespace App\Jobs;

use App\Model\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SettleDelayedProviderDeposit implements ShouldQueue
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
        Log::debug(__METHOD__ . 'Start ', [$this->transaction->getKey()]);

        $transactionUtil->settleToWallet($this->transaction);

        Log::debug(__METHOD__ . 'End', [$this->transaction->getKey()]);
    }
}
