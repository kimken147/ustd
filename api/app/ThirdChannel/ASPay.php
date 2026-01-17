<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class ASPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'ASPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://shunyang.aspay.one/api/newOrder';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://shunyang.aspay.one/withdraw/order';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://shunyang.aspay.one/withdraw/order/query';
    public $queryBalanceUrl = 'https://shunyang.aspay.one/merchant/balance';

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
        $this->key = $data['key'];
        $postBody = [
            "merchantId" => $data["merchant"],
            'orderId' => $data['request']->order_number,
            "channelType" => $data["key2"],
            'orderAmount' => strval($data['request']->amount),
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

        if ($row["code"] == 200) {
            $info = $row["data"];
            $ret = [
                'pay_url'   => $info["payUrl"] ?? '',
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

        if ($data["orderId"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], ['ok']) && in_array($transaction->type, [1])) {
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
        // $this->key = $data["key"];

        // $postBody = [
        //     "merchantCode" => $data["merchant"],
        // ];

        // try {
        //     $row = $this->sendRequest($data["queryBalanceUrl"], $postBody);
        //     if ($row["code"] == "00") {
        //         $balance = $row["data"];
        //         ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
        //             "balance" => $balance,
        //         ]);
        //         return $balance;
        //     }
        //     return 0;
        // } catch (\Throwable $th) {
        //     $message = $th->getMessage();
        //     Log::error(self::class, compact('data', 'message'));
        //     return 0;
        // }

        return 0;
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
        return md5($signStr);
    }
}
