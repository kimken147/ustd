<?php

namespace App\Console\Commands;

use App\Model\Transaction;
use App\Model\ThirdChannel;
use App\Utils\TransactionUtil;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class CheckFailDaifuOrderIsSuccess extends Command
{

    /**
     * @var string
     */
    protected $description = '确认三方代付狀態';
    /**
     * @var string
     */
    protected $signature = 'thirdchannel:check-daifu {channel?}';

    public function handle(TransactionUtil $transactionUtil)
    {

        $now = now();

        if ($this->argument('channel')) {
            $channel = ThirdChannel::find($this->argument('channel'));
        }

        $query = Transaction::where('status', Transaction::STATUS_FAILED)
            ->where('type', Transaction::TYPE_NORMAL_WITHDRAW)
            ->whereRaw("TIMESTAMPDIFF(day, created_at, '$now') <= 1");

        if ($this->argument('channel')) {
            $query->whereNull('thirdchannel_id');
        } else {
            $query->whereNotNull('thirdchannel_id');
        }

        $transactions = $query->get();

        if ($transactions->isNotEmpty()) {
            foreach($transactions as $transaction) {
                $channel = $transaction->thirdChannel;

                if (!$channel) {
                    if ($this->argument('channel')) {
                        $channel = ThirdChannel::find($this->argument('channel'));
                    } else {
                        continue;
                    }
                }

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

                $msg = $transaction->order_number .  '(' . $transaction->id . ') 失败原因: ' . $returnData['msg'];
                echo $msg . PHP_EOL;

                if (isset($returnData['status']) && $returnData['status'] == Transaction::STATUS_SUCCESS){
                    $msg = $transaction->order_number .  '(' . $transaction->id . ') 系统失败但是三方成功****';
                    echo $msg . PHP_EOL;
                    \Log::debug($msg);
                }
            }
        }

    }
}
