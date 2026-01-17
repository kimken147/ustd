<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use App\Utils\BCMathUtil;
use Carbon\Carbon;

class NNPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'NNPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api.nnpay.top/api/pay/1.0.0';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://api.nnpay.top/withdraw/order';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://api.nnpay.top/withdraw/order/query';
    public $queryBalanceUrl = 'https://api.nnpay.top/merchant/balance';

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
        Channel::CODE_UNION_QUICK_PASS => "KJ_DS",
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $math = new BCMathUtil;
        $postBody = [
            "mer_id" => $data["merchant"],
            'order_no' => $data['request']->order_number,
            'amount' => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分,
            "payment" => $this->channelCodeMap[$this->channelCode],
            "timestamp" => Carbon::now()->format('Y-m-d H:i:s'),
            'callback_url' => $data['callback_url'],
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

        if ($row["code"] == "00") {
            $info = json_decode($row["data"], true);
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $info["pay_url"] ?? '',
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
        return ["success" => false];
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all()["data"];
        $sign = $this->makesign($data, $thirdChannel->key);
        $math = new BCMathUtil;

        if ($sign != $data["sign"]) {
            return ["error" => "签名不正确"];
        }

        if ($data["order_no"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $math->mul($transaction->amount, 100, 0)) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["pay_status"], [1])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], [7])) {
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
        $signStr = urldecode(http_build_query($body) . "&secret=$key");
        return strtoupper(md5($signStr));
    }
}
