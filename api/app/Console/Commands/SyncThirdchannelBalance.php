<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Models\ThirdChannel;
use App\Utils\TransactionUtil;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncThirdchannelBalance extends Command
{

    /**
     * @var string
     */
    protected $description = '抓取餘額';
    /**
     * @var string
     */
    protected $signature = 'thirdchannel:sync-balance';

    public function handle(TransactionUtil $transactionUtil)
    {
        $syncThirdchannels = ThirdChannel::where('status', ThirdChannel::STATUS_ENABLE)->get();

        // 按 class + merchant_id 分組
        $groupedChannels = $syncThirdchannels->groupBy(function ($item) {
            return $item->class . '|' . $item->merchant_id;
        });

        foreach ($groupedChannels as $key => $channels) {
            try {
                // 取第一個作為代表進行查詢
                $syncThirdchannel = $channels->first();

                $path = "App\ThirdChannel\\" . $syncThirdchannel->class;
                $thirdchannelApi = new $path();

                if (!$thirdchannelApi->queryBalanceUrl) {
                    continue;
                }

                preg_match("/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/", $thirdchannelApi->queryBalanceUrl, $url);

                $data = [
                    'queryBalanceUrl'  => preg_replace("/{$url[1]}/", $syncThirdchannel->custom_url, $thirdchannelApi->queryBalanceUrl),
                    'merchant'  => $syncThirdchannel->merchant_id,
                    'key'  => $syncThirdchannel->key,
                    'key2'  => $syncThirdchannel->key2,
                    'key3'  => $syncThirdchannel->key3,
                    'key4'  => $syncThirdchannel->key4,
                    'proxy'  => $syncThirdchannel->proxy,
                    'thirdchannelId' => $syncThirdchannel->id,
                ];

                // 查詢余額
                $balance = $thirdchannelApi->queryBalance($data);

                // 一次更新該組所有記錄的余額
                if ($balance !== null && $balance !== false) {
                    $channelIds = $channels->pluck('id')->toArray();
                    ThirdChannel::whereIn('id', $channelIds)->update([
                        'balance' => $balance,
                        'updated_at' => now()
                    ]);
                }
            } catch (\Throwable $th) {
                Log::error("Error syncing balance for {$key}: " . $th->getMessage());
            }
        }
    }
}
