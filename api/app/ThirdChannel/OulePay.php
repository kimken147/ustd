<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Utils\BCMathUtil;

class OulePay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'OulePay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api.oule-b.com/api/collection';
    public $xiafaUrl   = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/payfor/trans';
    public $daifuUrl   = 'https://api.oule-b.com/api/payment';
    public $queryDepositUrl    = 'https://api.oule-b.com/api/pay/orderquery';
    public $queryDaifuUrl  = 'https://api.oule-b.com/api/deposit/inquire';
    public $queryBalanceUrl = 'https://api.oule-b.com/api/payment/merchant';

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
        Channel::CODE_BANK_CARD => "1",
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $math = new BCMathUtil;
        $this->key = $data['key'];
        $headers = [];
        $postBody = [
            "mch_id" => $data["merchant"],
            "nonce_str" => bin2hex(random_bytes(5)),
            "timeStamp" => time(),
            'orderNo' => $data['request']->order_number,
            'score' => $math->mul($data['request']->amount, 100, 0),
            'notify_url' => $data['callback_url'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['userName'] = $data['request']->real_name;
        }

        $postBody["sign"] = $this->makesign([
            "mch_id" => $data["merchant"],
            "nonce_str" => $postBody["nonce_str"],
            "timeStamp" => $postBody["timeStamp"],
            'orderNo' => $data['request']->order_number,
            'score' => $math->mul($data['request']->amount, 100, 0),
            "userName" => $postBody['userName'] ?? null
        ], $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody,
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class . " 代付", compact('data', 'postBody', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if ($row["code"] == "0") {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row["data"]['url'],
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ["success" => false];
        }
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
        $bankCode = $this->bankMap[$data['request']->bank_name];

        if (!$bankCode) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $postBody = [
            "mch_id" => $data["merchant"],
            "nonce_str" => bin2hex(random_bytes(5)),
            "timeStamp" => time(),
            'orderNo' => $data['request']->order_number,
            'userName' => $data['request']->bank_card_holder_name,
            'score' => $math->mul($data['request']->amount, 100, 0),
            "type" => 1,
            'bankName' => $data["request"]->bank_name,
            "branch" => "無",
            'cardId' => $data['request']->bank_card_number,
            'notify_url' => $data['callback_url'],
            "bank_code" => $bankCode,
        ];
        $postBody["sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if (isset($row["result"]) && $row["result"] == "ok") {
            return ["success" => true];
        }

        return ['success' => false];
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
            "mch_id" => $data["mch_id"],
            "nonce_str" => $data["nonce_str"],
            "orderNo" => $data["orderNo"],
            "score" => number_format($data["score"], 2, '.', ''),
            "timeStamp" => $data["timeStamp"]
        ], $thirdChannel->key);

        if (strtoupper($sign) != $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if (isset($data['orderNo']) && $data['orderNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '代付金额不正确'];
        }

        //代收检查状态
        if (isset($data['tradeState']) && in_array($data['tradeState'], ["SUCCESS"])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (isset($data['tradeState']) && in_array($data['tradeState'], ["fail"])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "mch_id" => $data["merchant"],
            "nonce_str" => $this->getRandomString(),
            "timeStamp" => time()
        ];
        $postBody["sign"] = $this->makesign($postBody, $this->key);
        $headers = [];

        try {
            $client = new Client();
            $response = $client->request('post', $data['queryBalanceUrl'], [
                'json' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        $row = json_decode($response->getBody(), true);

        if ($row["code"] == "0") {
            $balance = $row["data"]["quota"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }
        return 0;
    }

    public function makesign($data, $key)
    {
        unset($data["sign"]);
        ksort($data);
        $data = urldecode(http_build_query($data));
        $strSign = "$data&key=$key";
        $sign = md5($strSign);
        return $sign;
    }

    private function getRandomString(int $length = 5)
    {
        return bin2hex(random_bytes($length));
    }
}
