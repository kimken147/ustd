<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Model\ThirdChannel;
use App\Model\FeatureToggle;
use App\Notifications\ThirdchannelBalance;
use App\Repository\FeatureToggleRepository;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Cache;

class CheckThirdchannelBalance extends Command
{
    /**
     * @var string
     */
    protected $description = '定時判斷三方餘額';
    /**
     * @var string
     */
    protected $signature = 'thirdchannel:balance';

    public function handle(FeatureToggleRepository $featureToggleRepository)
    {
        if ($featureToggleRepository->enabled(FeatureToggle::NOTIFY_ADMIN_THIRD_CHANNEL_BALANCE)) {
            $thirdchannels = ThirdChannel::where('status', ThirdChannel::STATUS_ENABLE)->get();

            foreach ($thirdchannels as $thirdchannel) {
                $balance = (float)$thirdchannel->balance;
                $name = $thirdchannel->name;
                $merchant = $thirdchannel->merchant_id;
                $notify = $thirdchannel->notify_balance;
                $cacheKey = "thirdchannel_balance_$merchant";
                $balanceCache = Cache::get($cacheKey);

                if ($balance < $notify && $balance == $balanceCache) {
                    Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
                        ->notify(
                            new ThirdchannelBalance($name, $merchant, $balance)
                        );
                    Cache::put($cacheKey, $balance, now()->addDay());
                }
            }
        }
    }
}
