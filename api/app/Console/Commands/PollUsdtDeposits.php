<?php

namespace App\Console\Commands;

use App\Services\Crypto\CryptoMonitorService;
use Illuminate\Console\Command;

class PollUsdtDeposits extends Command
{
    protected $signature = 'usdt:poll-deposits';
    protected $description = '輪詢區塊鏈上的 USDT 收款交易';

    public function handle(CryptoMonitorService $service): int
    {
        $service->poll();
        return self::SUCCESS;
    }
}
