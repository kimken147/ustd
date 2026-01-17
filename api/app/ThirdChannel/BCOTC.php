<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class BCOTC extends ThirdChannel
{
    //Log名称
    public $log_name = 'BCOTC';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://api.bc-otc.app/api/v2/merchant-orders/';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://api.bc-otc.app/v1/order/withdraw';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://api.bc-otc.app';
    public $queryBalanceUrl = 'https://api.bc-otc.app/api/v2/merchants/balance/';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = 'OK';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "100",
        Channel::CODE_QR_ALIPAY => "210"
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "merchant_id" => $data["merchant"],
            'merchant_order_id' => $data['order_number'],
            "payment_method" => $data['key2'] ?? $this->channelCodeMap[$this->channelCode],
            'amount' => $data['request']->amount,
            'notify_url' => $data['callback_url'],
            "apply_timestamp" => time(),
        ];

        $realName = $data['request']->real_name ?? '';
        if (!$realName) {
            return ['success' => false, 'msg' => '没有实名'];
        }

        if (!$this->isChineseOnly($realName)) {
            return ['success' => false, 'msg' => '实名只能中文'];
        }

        $postBody['payer'] = $realName;

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

        if ($row["status"] == "1") {
            $info = $row["payment_information"];
            $ret = [
                'pay_url' => $row["payment_url"] ?? '',
                'receiver_bank_name' => $info["bank_name"] ?? '',
                'receiver_account' => $info["account_number"] ?? '',
                'receiver_name' => $info["account_name"] ?? '',
                'receiver_bank_branch' => $info["bank_branch"] ?? '',

            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ["success" => false, "msg" => $row["msg"]];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        return ["success" => false, '不支持代付'];
    }

    public function queryDaifu($data)
    {
        return ["success" => false, '不支持代付'];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($sign != $data["md5_sign"]) {
            return ["error" => "签名不正确"];
        }

        if (($data["merchant_order_id"] != $transaction->order_number) && ($data["merchant_order_id"] != $transaction->system_order_number)) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data["status"] == "completed") {
            return ['success' => true];
        }

        //代付检查状态，失败
        if ($data["status"] == "canceled" && in_array($transaction->type, [2, 4])) {
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
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", false);
            if ($row["status"] == "1") {
                $balance = $row["response"]["currency_conversion"]["available_assets"];
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
        $data["md5_sign"] = $this->makesign($data, $this->key);
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
        $signBody = array_merge($body, [
            "api_key" => $key
        ]);
        ksort($signBody);
        unset($signBody["md5_sign"]);
        $signStr = urldecode(json_encode($signBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return md5($signStr);
    }

    private function isChineseOnly($string)
    {// 匹配2個或以上的中文字符,且整個字串只能是中文
        return preg_match('/^[\x{4e00}-\x{9fa5}]{' . 2 . ',}$/u', $string) === 1;
    }
}
