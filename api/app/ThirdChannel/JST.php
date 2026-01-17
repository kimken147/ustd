<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class JST extends ThirdChannel
{
    //Log名称
    public $log_name   = 'JST';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://gl8881688.com/api/create4deposit';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://gl8881688.com/api/create4pay';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://gl8881688.com/api/query4pay';
    public $queryBalanceUrl = 'https://gl8881688.com/api/query4balance';

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
        Channel::CODE_BANK_CARD => "6001",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "merchant_id" => $data["merchant"],
            'payee_amount' => strval($data['request']->amount),
            'client_order_id' => $data['request']->order_number,
            "pay_mode_id" => $this->channelCodeMap[$this->channelCode],
            'callback_uri' => $data['callback_url'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['sender_name'] =  $data['request']->real_name;
        }

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

        if ($row["status"] == 1) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row["cashier_uri"] ?? '',
                'receiver_name' => $row["card_name"] ?? "",
                'receiver_bank_name' => $row["card_bank"] ?? "",
                'receiver_account' => $row["card_number"] ?? "",
                'receiver_bank_branch' => $row["card_branch"] ?? "",
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
        $this->key = $data['key'];

        $postBody = [
            "merchant_id" => $data["merchant"],
            "payee_name" => $data['request']->bank_card_holder_name,
            'payee_bank' => $data['request']->bank_name,
            'payee_number' => $data['request']->bank_card_number,
            'payee_amount' => strval($data['request']->amount),
            'client_order_id' => $data['request']->order_number,
            'callback_uri' => $data['callback_url'],
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }

        if ($result["status"] == 1) {
            return ["success" => true];
        }
        return ["success" => false, "msg" => $result["msg"] ?? ""];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postBody = [
            "merchant_id" => $data["merchant"],
            'client_order_id' => $data['request']->order_number,
            "payee_amount" => $data['request']->amount
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }

        if ($result["status"] == 1) {
            return ["success" => true];
        }
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($sign != $data["signature"]) {
            return ["error" => "签名不正确"];
        }

        if ($data["client_order_id"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["payee_amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["state"], [1009, 2009])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["state"], [2008])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "merchant_id" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", $debug = false);
            if ($row["status"] == 1) {
                $balance = $row["balance_0"];
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
        $data["signature"] = $this->makesign($data, $this->key);
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
        unset($body["signature"]);
        $signStr = urlencode(urldecode(http_build_query($body)) . "&hash_key=$key");
        return strtoupper(md5($signStr));
    }
}
