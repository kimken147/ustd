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

class Shanggu extends ThirdChannel
{
    //Log名称
    public $log_name = 'Shanggu';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://pay.fdbkjrgg.xyz/api/pay/unifiedorder';
    public $xiafaUrl = "";
    public $daifuUrl = '';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = '';
    public $queryBalanceUrl = 'https://pay.fdbkjrgg.xyz/api/mch/balance';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

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
        $math = new BCMathUtil();
        $postBody = [
            "mchId" => $data["merchant"],
            'wayCode' => (int)$data['key2'] ?? 901,
            "subject" => "Payment",
            'outTradeNo' => $data['request']->order_number,
            'amount' => $math->mul($data['request']->amount, 100, 0),
            'clientIp' => $data['request']->client_ip ?? '1.1.1.1',
            'notifyUrl' => $data['callback_url'],
            "returnUrl" => 'https://www.baidu.com/',
            'reqTime' => (int)now()->getPreciseTimestamp(3),
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            return [
                'success' => true,
                'data' => [
                    'pay_url' => $row['payUrl']
                ]
            ];
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
        return ["success" => false, "msg" => '不支援代付'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $math = new BCMathUtil;
        $data = $request->all();

        if ($data["outTradeNo"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $math->mul($transaction->amount, 100, 0)) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data["state"] == 1) {
            return ['success' => true];
        }

        return ['error' => "未知错误"];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $math = new BCMathUtil;
        $postBody = [
            "mchId" => $data["merchant"],
            'reqTime' => (int)now()->getPreciseTimestamp(3),
        ];

        $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", $debug = false);
        $balance = $math->div($row["balance"], 100, 2);
        ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
            "balance" => $balance,
        ]);
        return $balance;
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

            if ($row['code'] != '0') {
                throw new \Exception($row['message']);
            }

            return $row['data'];
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBody = json_decode($response->getBody()->getContents());
                $message = $responseBody['msg'] ?? $e->getMessage();
            }

            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = urldecode(http_build_query($body) . "&key=$key");
        return (md5($signStr));
    }
}
