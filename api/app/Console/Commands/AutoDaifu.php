<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\Transaction\DaiFuService;
use Illuminate\Console\Command;

class AutoDaifu extends Command
{
    protected $signature = 'daifu:auto';
    protected $description = '自動代付匹配：為匹配中的代付單分配出款帳號';

    public function handle(DaiFuService $service): int
    {
        if (!$service->checkAutoIsValid(config('app.region'))) {
            return self::SUCCESS;
        }

        $channelCodes = Channel::where('status', Channel::STATUS_ENABLE)
            ->pluck('code');

        foreach ($channelCodes as $code) {
            $service->execute($code);
        }

        return self::SUCCESS;
    }
}
