<?php

namespace App\Jobs;

use App\Models\UserChannelAccount;
use App\Services\Crypto\ChainTransactionSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class BackfillChainTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        public readonly int $accountId,
        public readonly int $days = 30,
    ) {}

    public function handle(ChainTransactionSyncService $syncService): void
    {
        $account = UserChannelAccount::find($this->accountId);
        if (!$account) {
            return;
        }

        $count = $syncService->backfillHistory($account, $this->days);

        Log::info('BackfillChainTransactions: 回溯完成', [
            'account_id' => $this->accountId,
            'days' => $this->days,
            'synced' => $count,
        ]);
    }
}
