<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ThirdChannel as ThirdChannelModel;

class JJPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'JJPay';
    public $type = 3; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://api.jj-pay.net/api/v2/merchant-collections/';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://api.jj-pay.net/api/v2/merchant-payout-requests/';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://api.jj-pay.net/api/v2/merchant-payout-requests/search/';
    public $queryBalanceUrl = 'https://api.jj-pay.ne/api/v2/merchants/balance/';

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
        Channel::CODE_BANK_CARD => "100"
    ];

    public $bankMap = [];
    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant_id" => $data["merchant"],
            "merchant_order_id" => $data["request"]->order_number,
            'amount' => $data['request']->amount,
            'notify_url' => $data['callback_url'],
            "payer" => $data["request"]->real_name,
            'payment_method' => $data['key2'] ?? $this->channelCodeMap[$this->channelCode],
            "apply_timestamp" => time(),
        ];

        $sign = $this->makesign($postBody, $this->key);
        $postBody["md5_sign"] = $sign;
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->depositUrl, [
                'json' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false];
        }
        $row = json_decode($response->getBody(), true);
        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if (isset($row['status']) && in_array($row['status'], ['1'])) {
            $payUrl = $row["payment_url"];
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url' => $payUrl ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        } else {
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
        if (!isset($this->bankMap[$data['request']->bank_name])) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }
        $bankCode = $this->bankMap[$data['request']->bank_name];

        $postBody = [
            "merchant_id" => $data["merchant"],
            "merchant_order_id" => $data["request"]->order_number,
            'amount' => $data['request']->amount,
            'notify_url' => $data['callback_url'],
            "bank_code" => $data["request"]->bank_name,
            "bank_branch" => "空",
            "account_name" => $data["request"]->bank_card_holder_name,
            "account_number" => $data["request"]->bank_card_number,
            "pay_type" => $data['key3'] ?? 100,
            "apply_timestamp" => time(),
        ];

        $postBody["md5_sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false];
        }
        $row = json_decode($response->getBody(), true);
        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if (isset($row['status']) && in_array($row['status'], ['1'])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant_order_id" => $data["request"]->order_number,
        ];
        $postBody["md5_sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
        $row = json_decode($response->getBody(), true);
        Log::debug(self::class, compact('data', 'postBody', 'response'));

        if (isset($row['status']) && in_array($row['status'], [1])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($data["md5_sign"] != $sign) {
            return ["error" => "簽名錯誤"];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }
        if ($data['merchant_order_id'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['status']) && in_array($data['status'], ["completed"])) {
            return ['success' => true];
        } else if (isset($data['status']) && in_array($data['status'], ["canceled"])) {
            return ['fail' => $data["pay_result"]];
        }
        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant_id" => $data["merchant"],
        ];
        $postBody["md5_sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryBalanceUrl'], [
                'json' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        $row = json_decode($response->getBody(), true);
        if ($row["status"] == 1) {
            $balance = $row["response"]["total_assets"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }
        return 0;
    }

    public function makesign($body, $key)
    {
        unset($body["md5_sign"]);
        $body["api_key"] = $key;
        ksort($body);
        $signStr = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return md5($signStr);
    }
}
