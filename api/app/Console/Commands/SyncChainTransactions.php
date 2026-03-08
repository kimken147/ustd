<?php

namespace App\Console\Commands;

use App\Services\Crypto\ChainTransactionSyncService;
use App\Services\Crypto\ChainTransactionMatchService;
use Illuminate\Console\Command;

class SyncChainTransactions extends Command
{
    protected $signature = 'chain:sync-transactions';
    protected $description = '同步所有 USDT 帳號的鏈上交易（補漏）';

    public function handle(
        ChainTransactionSyncService $syncService,
        ChainTransactionMatchService $matchService,
    ): int {
        $this->info('開始同步鏈上交易...');

        $result = $syncService->syncAllAccounts();
        $this->info("同步完成：{$result['accounts']} 個帳號，新增 {$result['synced']} 筆交易");

        // 補漏排程結束後重新比對 pending 記錄
        $matched = $matchService->matchPendingTransactions();
        $this->info("重新比對完成：{$matched} 筆匹配");

        return self::SUCCESS;
    }
}
