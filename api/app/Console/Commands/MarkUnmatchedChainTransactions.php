<?php

namespace App\Console\Commands;

use App\Services\Crypto\ChainTransactionMatchService;
use Illuminate\Console\Command;

class MarkUnmatchedChainTransactions extends Command
{
    protected $signature = 'chain:mark-unmatched {--hours=24}';
    protected $description = '將超過指定時間的 pending 鏈上交易標記為 unmatched';

    public function handle(ChainTransactionMatchService $matchService): int
    {
        $hours = (int) $this->option('hours');
        $count = $matchService->markStaleAsUnmatched($hours);
        $this->info("已將 {$count} 筆超過 {$hours} 小時的交易標記為 unmatched");

        return self::SUCCESS;
    }
}
