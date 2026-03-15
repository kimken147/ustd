<?php

namespace App\Jobs;

use App\Models\UserChannelAccount;
use App\Services\Crypto\ConsolidationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConsolidateChildAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        private readonly int $childAccountId,
    ) {}

    public function handle(ConsolidationService $service): void
    {
        $child = UserChannelAccount::find($this->childAccountId);
        if (!$child || !$child->parent_account_id) {
            return;
        }

        $result = $service->consolidate($child);

        Log::info('ConsolidateChildAccount: 歸集結果', [
            'child_account_id' => $this->childAccountId,
            'result' => $result,
        ]);
    }
}
