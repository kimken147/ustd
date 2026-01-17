<?php

namespace App\Console\Commands;

use App\Model\Transaction;
use App\Model\MerchantThirdChannel;
use App\Utils\TransactionUtil;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class SyncThirdchannelDaifuOrder extends Command
{

    /**
     * @var string
     */
    protected $description = '定時抓取三方代付狀態';
    /**
     * @var string
     */
    protected $signature = 'thirdchannel:sync-daifu';

    public function handle(TransactionUtil $transactionUtil)
    {

        $now = now();

        $transactions = Transaction::whereIn('status', [Transaction::STATUS_THIRD_PAYING, Transaction::STATUS_RECEIVED])
            ->where('type', Transaction::TYPE_NORMAL_WITHDRAW)
            ->whereRaw("TIMESTAMPDIFF(day, created_at, '$now') <= 1")
            ->whereHas('thirdChannel', function (Builder $thirdChannel) {
                $thirdChannel->where('sync', 1);
            })
            ->get();
        if ($transactions->isNotEmpty()) {
            foreach($transactions as $transaction) {
                $channel = $transaction->thirdChannel;

                $path = "App\ThirdChannel\\".$channel->class;
                $thirdchannel = new $path();

                preg_match("/^(?:https?:\/\/)?(?:[^@\n]+@)?(?:www\.)?([^:\/\n?]+)/", $thirdchannel->daifuUrl,$url);

                $newData = new \stdClass();
                $newData->order_number = $transaction->order_number;
                $newData->amount = $transaction->amount;

                $data = [
                    'queryDaifuUrl'  => preg_replace("/{$url[1]}/", $channel->custom_url, $thirdchannel->queryDaifuUrl),
                    'merchant'  => $channel->merchant_id,
                    'key'  => $channel->key,
                    'key2'  => $channel->key2,
                    'key3'  => $channel->key3,
                    'proxy' => $channel->proxy,
                    'request' => $newData,
                ];

                $returnData = $thirdchannel->queryDaifu($data);

                if ($returnData['success'] && isset($returnData['status']) && $returnData['status'] == Transaction::STATUS_FAILED){
                    $transactionUtil->markAsFailed($transaction, null, $returnData['msg'], false);
                } if ($returnData['success'] && isset($returnData['status']) && $returnData['status']  == Transaction::STATUS_SUCCESS){
                    $transactionUtil->markAsSuccess($transaction, null, true, false, false);
                }
            }
        }

    }
}
