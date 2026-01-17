<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ThirdChannel as ThirdChannelModel;

class Pay17 extends ThirdChannel
{
    //Log名称
    public $log_name = 'Pay17';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://api.ocean-flux.com/api/store/storereceive/add';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://api.ocean-flux.com/api/store/storepay/add';
    public $queryDepositUrl = 'https://query.zhangcheng888.com/api/pay/query_order';
    public $queryDaifuUrl = 'https://api.ocean-flux.com/api/store/storepay/info';
    public $queryBalanceUrl = 'https://api.ocean-flux.com/api/store/info';

    //预设商户号
    public $merchant = '';

    //预设密钥
    public $key = '';
    public $key2 = '';
    public $key3 = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "0"
    ];

    public $bankMap = [];


    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "account" => $data["merchant"],
            'type' => $this->channelCodeMap[$this->channelCode],
            "tradeno" => $data["request"]->order_number,
            'money' => $data['request']->amount,
            'notify_url' => $data['callback_url'],
            "notify_limited" => 2
        ];

        $postBody["token"] = md5($postBody["account"] . $postBody["tradeno"] . $this->key);

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['trans_name'] = $data['request']->real_name;
        }

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->depositUrl, [
                "form_params" => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
            Log::debug(self::class, compact('data', 'postBody', 'row'));
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'      => $row['url']
            ];
            return ["success" => true, "data" => $ret];
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "account" => $data["merchant"],
            "tradeno" => $data["request"]->order_number,
            'money' => $data['request']->amount,
            "inname" => $data["request"]->bank_card_holder_name,
            "inbankname" => $data["request"]->bank_name,
            "inbanknum" => $data["request"]->bank_card_number,
            'notify_url' => $data['callback_url'],
            "notify_limited" => 2
        ];

        $postBody["token"] = md5($postBody["account"] . $postBody["tradeno"] . $this->key);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->daifuUrl, [
                'form_params' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
            Log::debug(self::class, compact('data', 'postBody', 'row'));
            return  ["success" => true];
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false];
        }
    }

    public function queryDaifu($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "account" => $data["merchant"],
            "tradeno" => $data["request"]->order_number,
        ];
        $postBody["token"] = md5($postBody["account"] . $postBody["tradeno"] . $this->key);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
                'form_params' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = md5($data["store_account"]  . $thirdChannel->key . $data["tradeno"]);
        if ($data["token"] != $sign) {
            return ["error" => "簽名錯誤"];
        }

        if (isset($data['money']) && $data['money'] != ($transaction->amount)) {
            return ['error' => '金额不正确'];
        }

        if ($data["tradeno"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["status"] == 4) {
            return ['success' => true];
        }

        if ($data["status"] == 3) {
            return ['fail' => '逾時'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "account" => $data["merchant"],
            "token" => md5($data["merchant"] . $this->key)
        ];

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $this->queryBalanceUrl, [
                "query" => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return 0;
        }

        $row = json_decode($response->getBody(), true);
        $balance = $row["data"]["store_money"];
        ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
            "balance" => $balance,
        ]);
        return $balance;
    }

    public function makesign($body, $key)
    {
        unset($body["sign"]);
        $signStr = "";
        foreach ($body as $key => $value) {
            $signStr = $signStr . $value;
        }
        return md5($signStr);
    }
}
