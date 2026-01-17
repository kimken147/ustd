<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Illuminate\Support\Str;

class EPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'EPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://api.acent.cc/api/txs/pay/NativePayment';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://api.acent.cc/withdraw/order';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://api.acent.cc/withdraw/order/query';
    public $queryBalanceUrl = 'https://api.acent.cc/api/acc/accountQuery';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "BankToBank",
        Channel::CODE_QR_ALIPAY => "930"
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $this->merchant = $data['merchant'];
        $postBody = [
            'version' => '3.0',
            'outTradeNo' => $data['request']->order_number,
            "customerCode" => $data['key2'],
            'orderInfo' => [
                'id' => $data['request']->order_number,
                'businessType' => '130003',
                'goodsList' => [
                    'name' => '商品名称',
                    'number' => '1',
                    'amount' => $this->bcMathUtil->mul($data['request']->amount, 100, 0),
                ]
            ],
            'payMethod' => $data['key3'],
            'payAmount' => $this->bcMathUtil->mul($data['request']->amount, 100, 0),
            'payCurrency' => 'CNY',
            'notifyUrl' => $data['callback_url'],
            'transactionStartTime' => now()->format('YmdHis'),
            'areaInfo' => '130581',
            'nonceStr' => Str::random(32),
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            $ret = [
                'pay_url' => $row["codeUrl"] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data["outTradeNo"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $this->bcMathUtil->mul($transaction->amount, 100, 0)) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data["payState"] == "00") {
            return ['success' => true, 'resBody' => [
                'returnCode' => '0000'
            ]];
        }

        //代付检查状态，失败
        // if (in_array($data["status"], [4])) {
        //     return ['fail' => '逾时'];
        // }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $this->merchant = $data["merchant"];

        $postBody = [
            'nonceStr' => Str::random(32),
            "customerCode" => $data["key2"]
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", false);
            $balance = $this->bcMathUtil->div($row["floatBalance"], 100, 2);
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            Log::error(self::class, compact('data', 'message'));
            return 0;
        }
    }

    private function getHeaders(string $merchantId, string $timestamp, array $body) {
        return [
            'x-efps-sign-no' => $merchantId,
            'x-efps-sign-type' => 'RSAwithSHA256',
            'x-efps-sign' => $this->makeSign($body, $this->key),
            'x-efps-timestamp' => $timestamp,
        ];
    }

    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $timestamp = now()->format('YmdHis');
        $headers = $this->getHeaders($this->merchant, $timestamp, $data);
        try {
            $client = new Client();
            $response = $client->request($method, $url, [
                'headers' => $headers,
                "json" => $data,
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['returnCode'] != '0000') {
                throw new Exception($row['returnMsg']);
            }

            return $row;
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message = json_decode($response->getBody()->getContents());
            $message = $message->message ?? "";
            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        $signStr = json_encode($body);
        $private_key = openssl_pkey_get_private($key, 111111);
        openssl_sign($signStr, $signature, $private_key, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }
}
