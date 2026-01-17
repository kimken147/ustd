<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class Shopee extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Shopee';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api.tw-shopee.net/v1/order/receive';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://api.tw-shopee.net/v1/order/withdraw';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = '';
    public $queryBalanceUrl = 'https://api.tw-shopee.net/v1/client/balance';

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

    private function getHeaders($clientId, $accessToken)
    {
        return [
            "CLIENTSID" => $clientId,
            "ACCESSTOKEN" => $accessToken
        ];
    }


    /*   代收   */
    public function sendDeposit($data)
    {
        $this->merchant = $data["merchant"];
        $this->key = $data['key'];
        $postBody = [
            'order_id' => $data['request']->order_number,
            'amount' => $data['request']->amount,
            'callback' => $data['callback_url'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['payer'] =  $data['request']->real_name;
        }

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false];
        }

        if ($row["code"] == 200) {
            $info = $row["data"];
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $info["fronttable_url"] ?? '',
                "receiver_bank_name" => $info["bank_name"] ?? "",
                'receiver_name' => $info['bank_title'] ?? '',
                'receiver_account' => $info['bank_no'] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        }

        return ["success" => false];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $this->merchant = $data["merchant"];

        $postBody = [
            'order_id' => $data['request']->order_number,
            'amount' => $data['request']->amount,
            "payer" => $data['request']->bank_card_holder_name,
            'bank_title' => $data['request']->bank_card_holder_name,
            'bank_name' => $data["request"]->bank_name,
            'bank_no' => $data['request']->bank_card_number,
            'callback' => $data['callback_url'],
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }

        if ($result["code"] == 200) {
            return ["success" => true];
        }
        return ["success" => false];
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data["order_id"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], [3])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], [96])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $this->merchant = $data["merchant"];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], null, "get", false);
            if ($row["code"] == 200) {
                $balance = $row["balance"];
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
        try {
            $client = new Client();
            $response = $client->request($method, $url, [
                "json" => $data,
                "headers" => $this->getHeaders($this->merchant, $this->key)
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $e;
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = http_build_query($body) . "&key=$key";
        return md5($signStr);
    }
}
