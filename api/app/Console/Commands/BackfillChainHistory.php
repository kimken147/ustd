<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\UserChannelAccount;
use App\Services\Crypto\ChainTransactionSyncService;
use Illuminate\Console\Command;

class BackfillChainHistory extends Command
{
    protected $signature = 'chain:backfill-history {--days=30} {--account_id=}';
    protected $description = '回溯拉取鏈上交易歷史';

    public function handle(ChainTransactionSyncService $syncService): int
    {
        $days = (int) $this->option('days');
        $accountId = $this->option('account_id');

        if ($accountId) {
            $accounts = UserChannelAccount::where('id', $accountId)
                ->where('channel_code', Channel::CODE_USDT)
                ->get();
        } else {
            $accounts = UserChannelAccount::where('channel_code', Channel::CODE_USDT)
                ->whereNull('deleted_at')
                ->get();
        }

        if ($accounts->isEmpty()) {
            $this->warn('沒有找到符合條件的 USDT 帳號');
            return self::SUCCESS;
        }

        $this->info("開始回溯 {$days} 天的歷史交易，共 {$accounts->count()} 個帳號...");
        $bar = $this->output->createProgressBar($accounts->count());

        $totalCount = 0;
        foreach ($accounts as $account) {
            try {
                $count = $syncService->backfillHistory($account, $days);
                $totalCount += $count;
                $this->line(" 帳號 {$account->id}: 新增 {$count} 筆");
            } catch (\Exception $e) {
                $this->error(" 帳號 {$account->id} 失敗: {$e->getMessage()}");
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("回溯完成：共新增 {$totalCount} 筆交易");

        return self::SUCCESS;
    }
}
