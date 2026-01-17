<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

use App\Models\Channel;
use App\Models\Transaction;
use App\Utils\TransactionUtil;
use App\Utils\BCMathUtil;
use Illuminate\Support\Facades\Log;

class SyncUsdtTransaction extends Command
{
    /**
     * @var string
     */
    protected $description = '定時抓取 USDT 代收';
    /**
     * @var string
     */
    protected $signature = 'usdt:sync-transaction';

    public function handle(TransactionUtil $transactionUtil, BCMathUtil $bcmath)
    {
        $now = now();

        $transactions = Transaction::where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->where('status', Transaction::STATUS_PAYING)
            ->where('channel_code', Channel::CODE_USDT)
            ->where('created_at', '>', $now->subHour())
            ->get();

        $transactions = $transactions->groupBy(function ($transaction) {
            return $transaction->from_channel_account['account'];
        });

        foreach ($transactions as $address => $group) {
            $result = Http::get("https://apilist.tronscan.org/api/token_trc20/transfers?limit=20&start=0&sort=-timestamp&count=true&tokens=TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t&relatedAddress={$address}")->json();

            if (!isset($result['token_transfers']))  {
                continue;
            }

            $transfers = $result['token_transfers'];

            foreach ($group as $transaction) {
                $matched = collect($transfers)->filter(function ($transfer) use ($transaction, $bcmath, $address) {
                    // 商戶要求一個會員就一直是同個帳號，所以不使用錢包匹配
                    // if (isset($transaction->to_channel_account['real_name']) && $transfer['from_address'] != $transaction->to_channel_account['real_name']) { // 錢包地址跟轉帳地址一樣
                    //     return false;
                    // }

                    if ($transfer['finalResult'] != 'SUCCESS' || !$transfer['confirmed']) {
                       return false;
                    }

                    if (!$bcmath->eq($bcmath->mul($transaction->floating_amount, 1000000, 0), $transfer['quant'])) { // 金額相等
                        return false;
                    }

                    if ($transfer['block_ts'] < $transaction->matched_at->getPreciseTimestamp(3)) { // 完成時間 需要大於 匹配時間
                        return false;
                    }

                    if ($transfer['to_address'] != $address) { // 收款地址相同
                        return false;
                    }

                    return true;
                });

                foreach ($matched as $transfer) {
                    $isExists = Transaction::where('to_channel_account->transaction_id', $transfer['transaction_id'])->exists();

                    if (!$isExists) { // transaction_id 未被使用才能上分
                        $to = $transaction->to_channel_account;
                        $to['transaction_id'] = $transfer['transaction_id'];
                        $transaction->update(['to_channel_account' => $to]);

                        $transactionUtil->markAsSuccess($transaction, null, true, false, false);
                    }
                }
            }
        }
    }
}
