<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Utils\BCMathUtil;

class RSPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'RSPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://www.rspay88.com/louis/gateway.do';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://www.rspay88.com/louis/ap.do';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://bebepay.net/bebeApi/v1.1/orders/payment_transfer_query';
    public $queryBalanceUrl = 'https://www.rspay88.com/louis/spQuery.do';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'SUCCESS';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "17",
        Channel::CODE_QR_ALIPAY => "17"
    ];

    public $bankMap = [
        "中国工商银行" => "001",
        "工商银行" => "001",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $math = new BCMathUtil;
        $postBody = [
            "version" => "1.6",
            "cid" => $data["merchant"],
            'tradeNo' => $data['request']->order_number,
            'amount' => strval($math->mul($data['request']->amount, 100, 0)),  // 金額單位是分
            'payType' => $data["key2"] ?? $this->channelCodeMap[$this->channelCode],
            "requestTime" => date('YmdHis'),
            'notifyUrl' => $data['callback_url'],
            "returnType" => "0"
        ];

        if (isset($data["key2"])) {
            $postBody["payType"] = $data["key2"];
        }

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['acctName'] = $data['request']->real_name;
        }

        $postBody["sign"] = $this->makesign($data, $data["key"]);

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, 'msg' => $th->getMessage()];
        }

        if ($row["retcode"] == 0) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row['link'] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        }

        return ["success" => false, 'msg' => $row['retmsg'] ?? ''];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $math = new BCMathUtil;
        // $bankCode = $this->bankMap[$data['request']->bank_name];

        // if (!$bankCode) {
        //     return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        // }

        $postBody = [
            "version" => "1.0",
            "cid" => $data["merchant"],
            'tradeNo' => $data['request']->order_number,
            "requestTime" => date('YmdHis'),
            'amount' => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分
            "payType" => 1,
            'acctName' => $data['request']->bank_card_holder_name,
            'acctNo' => $data['request']->bank_card_number,
            'bankCode' => $this->bankMap[$data["request"]->bank_name],
            'notifyUrl' => $data['callback_url'],
        ];

        $postBody["sign"] = $this->makesign($data, $data["key"]);

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }
        if ($result["retcode"] == 0) {
            return ["success" => true];
        } else {
            return ['success' => false];
        }
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);
        $math = new BCMathUtil;

        if ($sign != $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if ($data["tradeNo"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($data["amount"] != $math->mul($transaction->amount, 100, 0)) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], [1])) {
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
            "cid" => $data["merchant"],
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
        if ($row["retcode"] == 0) {
            $balance = $row["balancePay"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }

        return 0;
    }

    private function sendRequest($url, $data)
    {
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'json' => $data
            ]);
            $row = json_decode($response->getBody(), true);
            Log::debug(self::class, compact('data', 'row'));
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $e;
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        unset($body["sign"]);
        ksort($body);
        $signStr = urldecode(http_build_query($body)) . "&key=$key";
        return md5($signStr);
    }
}
