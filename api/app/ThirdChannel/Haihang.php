<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class Haihang extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Haihang';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://haihang-api.tszf66.com/api/v1/payment/init';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://haihang-api.tszf66.com/withdraw/order';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://haihang-api.tszf66.com/withdraw/order/query';
    public $queryBalanceUrl = 'https://haihang-api.tszf66.com/api/v1/merchant/account';

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
        Channel::CODE_BANK_CARD => "BankToBank",
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "mchKey" => $data["merchant"],
            "product" => $data["key2"],
            'mchOrderNo' => $data['request']->order_number,
            'amount' => $this->bcMathUtil->mul($data['request']->amount, 100, 0),  // 金額單位是分
            'nonce' => Str::random(rand(8, 16)),
            'timestamp' => now()->timestamp * 1000,
            'notifyUrl' => $data['callback_url'],
        ];

        // if (isset($data['request']->real_name) && $data['request']->real_name != '') {
        //     $postBody['applyUserName'] =  $data['request']->real_name;
        // }

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

        $ret = [
            'pay_url'   => $row['url']["payUrl"] ?? '',
        ];
        return ['success' => true, 'data' => $ret];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        // $this->key = $data['key'];
        // $bankCode = $this->bankMap[$this->normalizeChineseCharacters($data['request']->bank_name)] ?? null;
        // if (!$bankCode) {
        //     return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        // }

        // $postBody = [
        //     "merchantCode" => $data["merchant"],
        //     'merchantOrderId' => $data['request']->order_number,
        //     "serviceId" => $data["key3"],
        //     'applyAmount' => strval($data['request']->amount),
        //     "applyUserName" => $data['request']->bank_card_holder_name,
        //     'applyAccount' => $data['request']->bank_card_number,
        //     'callbackUrl' => $data['callback_url'],
        //     'applyBankName' => $data["request"]->bank_name,
        //     'applyBankCode' => $bankCode,
        // ];

        // try {
        //     $result = $this->sendRequest($data["url"], $postBody);
        // } catch (\Exception $e) {
        //     return ['success' => false];
        // }

        // if ($result["code"] == "00") {
        //     return ["success" => true];
        // }
        return ["success" => false];
    }

    public function queryDaifu($data)
    {
        // $this->key = $data['key'];

        // $postBody = [
        //     "merchantCode" => $data["merchant"],
        //     'merchantOrderId' => $data['request']->order_number,
        // ];

        // try {
        //     $result = $this->sendRequest($data["url"], $postBody);
        // } catch (\Exception $e) {
        //     return ['success' => false];
        // }

        // if ($result["code"] == "00") {
        //     return ["success" => true];
        // }
        // return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        return ['success' => false];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        // $sign = $this->makesign($data, $thirdChannel->key);

        // if ($sign != $data["sign"]) {
        //     return ["error" => "签名不正确"];
        // }

        if ($data["mchOrderNo"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $this->bcMathUtil->mul($transaction->amount, 100, 0)) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["payStatus"], ['SUCCESS']) && in_array($transaction->type, [1])) {
            return ['success' => true];
        }

        //代付检查状态
        if (in_array($data["status"], [3]) && in_array($transaction->type, [4])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], [4])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "mchKey" => $data["merchant"],
            'nonce' => Str::random(rand(8, 16)),
            'timestamp' => now()->timestamp * 1000
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, 'POST', false);
            return $this->bcMathUtil->div($row['defaultAccount']['totalBalance'] - $row['defaultAccount']['freezeBalance'], 100, 2);
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

            if ($row["code"] != '200') {
                throw new Exception($row["msg"]);
            }

            return $row['data'];
        } catch (Exception $e) {
            Log::error(self::class, compact('data', 'e'));
            throw new Exception($e->getMessage());
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = urldecode(http_build_query($body) . "$key");
        return md5($signStr);
    }
}
