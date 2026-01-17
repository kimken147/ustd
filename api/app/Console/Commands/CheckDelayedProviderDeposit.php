<?php

namespace App\Console\Commands;

use App\Model\Transaction;
use App\Jobs\SettleDelayedProviderDeposit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CheckDelayedProviderDeposit extends Command
{

    /**
     * @var string
     */
    protected $description = '碼商延遲上分';
    /**
     * @var string
     */
    protected $signature = 'paufen:check-provider-delayed-deposit';

    public function handle()
    {
        $batch = rand();

        $transactions = Transaction::whereIn('status',
            [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->where('to_wallet_settled', false)
            ->where('to_wallet_should_settled_at', '>=', now()->subMinutes(3))
            ->where('to_wallet_should_settled_at', '<=', now()->addMinutes(3))
            ->get();

        foreach ($transactions as $transaction) {
            $cacheKey = "delayed_deposits_dispatched_{$transaction->getKey()}";

            if (Cache::get($cacheKey)) {
                continue;
            }

            Cache::put($cacheKey, true, now()->addMinutes(10));

            SettleDelayedProviderDeposit::dispatch($transaction)->delay($transaction->to_wallet_should_settled_at);
        }
    }
}
