<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use App\Utils\BCMathUtil;

class FeiHou extends ThirdChannel
{
    //Log名称
    public $log_name   = 'FeiHou';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://paygateway.stmshopping.com/api/pay/unifiedOrderH5';
    public $xiafaUrl   = "";
    public $daifuUrl   = 'https://paygateway.stmshopping.com/api/withdraw/unifiedOrder';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://paygateway.stmshopping.com/api/withdraw/query';
    public $queryBalanceUrl = 'https://paygateway.stmshopping.com/api/mchInfo/queryBalance';

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
        Channel::CODE_BANK_CARD => "BankToBank",
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $math = new BCMathUtil;
        $this->key = $data['key'];
        $postBody = [
            "mchNo" => $data["merchant"],
            "appId" => $data["key2"],
            'mchOrderNo' => $data['request']->order_number,
            'amount' => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分,
            "currency" => "cny",
            "subject" => "收银台订单",
            "body" => "收银台订单",
            'notifyUrl' => $data['callback_url'],
            "reqTime" => time(),
            "version" => "1.0",
            "signType" => "MD5"
        ];

        // if (isset($data['request']->real_name) && $data['request']->real_name != '') {
        //     $postBody['applyUserName'] =  $data['request']->real_name;
        // }

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

        if ($row["code"] == "0") {
            $info = $row["data"] ?? "";
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $info ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        }

        return ["success" => false, "msg" => $row["msg"] ?? ""];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $math = new BCMathUtil;
        $this->key = $data['key'];

        $postBody = [
            "mchNo" => $data["merchant"],
            "appId" => $data["key2"],
            'mchOrderNo' => $data['request']->order_number,
            "wayCode" => "SYWL-XSPAY-DF",
            "bankCardType" => "1",
            'amount' => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分,
            "bankCardUserName" => $data['request']->bank_card_holder_name,
            "bankCardNo" => $data['request']->bank_card_number,
            "currency" => "CNY",
            'notifyUrl' => $data['callback_url'],
            "reqTime" => time(),
            "version" => "1.0",
            "signType" => "MD5"
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }

        if ($result["code"] == "0") {
            return ["success" => true];
        }
        return ["success" => false, "msg" => $request["msg"] ?? ""];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postBody = [
            "mchNo" => $data["merchant"],
            "appId" => $data["key2"],
            'mchOrderNo' => $data['request']->order_number,
            "reqTime" => time(),
            "version" => "1.0",
            "signType" => "MD5"
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }

        if ($result["code"] == "0") {
            return ["success" => true];
        }
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $math = new BCMathUtil;
        $data = $request->all();
        if (isset($data["data"])) {
            $data = $data["data"];
        }
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($sign != $data["sign"]) {
            return ["error" => "签名不正确"];
        }

        if ($data["mchOrderNo"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data["applyAmount"]) && $data["applyAmount"] != $math->mul($transaction->amount, 100, 0)) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ((isset($data["state"]) && in_array($data["state"], [2])) || (isset($data["orderState"]) && $data["orderState"] == 2)) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if ((isset($data["state"]) && in_array($data["state"], [3, 6])) || (isset($data["orderState"]) && in_array($data["orderState"], [3, 6]))) {
            return ['fail' => '逾时'];
        }

        return ['error' => "未知错误"];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "mchNo" => $data["merchant"],
            "appId" => $data["key2"],
            "reqTime" => time(),
            "version" => "1.0",
            "signType" => "MD5"
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", $debug = false);
            if ($row["code"] == "0") {
                $balance = $row["data"]["balanceAmount"];
                ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                    "balance" => $balance,
                ]);
                return $balance;
            }
            return 0;
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            Log::error(self::class, compact('data', 'message'));
            return 0;
        }
    }

    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $response = $client->request($method, $url, [
                "json" => $data,
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message = json_decode($response->getBody()->getContents());
            $message = $message->message ?? "";
            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = urldecode(http_build_query($body) . "&key=$key");
        return strtoupper(md5($signStr));
    }
}
