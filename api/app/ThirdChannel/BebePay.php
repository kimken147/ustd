<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class BebePay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'BebePay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://bebepay.net/bebeApi/v1.1/order/create';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://bebepay.net/bebeApi/v1.0/payment/create';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://bebepay.net/bebeApi/v1.1/orders/payment_transfer_query';
    public $queryBalanceUrl = 'https://bebepay.net/bebeApi/v1.0/balance';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => '4',
        Channel::CODE_QR_ALIPAY => "7",
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "timestamp" => strval(time()),
            'amount' => strval($data['request']->amount),
            "appKey" => $data["merchant"],
            'payType' => $this->channelCodeMap[$this->channelCode],
            'orderID' => $data['request']->order_number,
            'callback_url' => $data['callback_url'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['real_name'] = $data['request']->real_name;
        } else {
            $postBody['real_name'] = "無";
        }

        $postBody["sign"] = $this->makesign([
            "timestamp" => strval($postBody["timestamp"]),
            'amount' => strval($data['request']->amount),
            "appKey" => $data["merchant"],
            'payType' => $this->channelCodeMap[$this->channelCode],
            'orderID' => $data['request']->order_number,
            "real_name" => $postBody['real_name']
        ], $data["key"]);

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, 'msg' => $th->getMessage()];
        }

        if ($row["success"]) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row['pay_url'] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        }

        return ["success" => false, 'msg' => $row["message"]];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        // $bankCode = $this->bankMap[$data['request']->bank_name];

        // if (!$bankCode) {
        //     return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        // }

        $postBody = [
            "timestamp" => strval(time()),
            'amount' => strval($data['request']->amount),
            "appKey" => $data["merchant"],
            'bankName' => $data["request"]->bank_name,
            'accountName' => $data['request']->bank_card_holder_name,
            'accountNumber' => $data['request']->bank_card_number,
            'orderID' => $data['request']->order_number,
            'callback_url' => $data['callback_url'],
        ];

        $postBody["sign"] = $this->makesign([
            "timestamp" => strval($postBody["timestamp"]),
            'amount' => strval($data['request']->amount),
            "appKey" => $data["merchant"],
            'bankName' => $data["request"]->bank_name,
            'accountName' => $data['request']->bank_card_holder_name,
            'accountNumber' => $data['request']->bank_card_number,
            'orderID' => $data['request']->order_number,
        ], $data["key"]);

        try {
            $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }
        return ["success" => true];
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign([
            "type" => $data["type"],
            "amount" => $data["amount"],
            "real_amount" => $data["real_amount"],
            "appKey" => $data["appKey"],
            "payType" => $data["payType"],
            "orderID" => $data["orderID"],
            "status" => $data["status"]
        ], $thirdChannel->key);

        if ($sign != $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if ($data["orderID"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($data["amount"] != $transaction->amount || $data["real_amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], [4])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], [3])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "appKey" => $data["merchant"],
        ];

        try {
            $client = new Client();
            $response = $client->request('POST', $data["queryBalanceUrl"], [
                'json' => $postBody
            ]);
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $th;
        }
        $row = json_decode($response->getBody(), true);
        if ($row["message"] == "Success") {
            $balance = $row["balance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }

        return 0;
    }

    private function sendRequest($url, $data)
    {
        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'json' => $data
            ]);
            $row = json_decode($response->getBody(), true);
            Log::debug(self::class, compact('data', 'row'));
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, [
                'data' => $data,
                'message' => $message,
            ]);
            throw $e;
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        unset($body["sign"]);
        $signStr = implode("&", array_values($body)) . "&$key";
        return md5($signStr);
    }
}
