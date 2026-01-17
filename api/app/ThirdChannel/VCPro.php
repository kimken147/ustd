<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Predis\Response\ServerException;

class VCPro extends ThirdChannel
{
    //Log名称
    public $log_name = 'VCPro';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://vcotc.net/api/otc/usdt/deposit_v1.1';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://vcotc.net/withdraw/order';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://vcotc.net/api/v1.0/order/status';
    public $queryBalanceUrl = 'https://vcotc.net/api/v1.0/balance';

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
        Channel::CODE_BANK_CARD => "4",
        Channel::CODE_QR_ALIPAY => "7",
        Channel::CODE_QR_WECHATPAY => '3'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "timestamp" => strval(time()),
            'amount' => strval($data['request']->amount),
            "appKey" => $data["merchant"],
            "payType" => empty($data['key2']) ? $this->channelCodeMap[$this->channelCode] : $data['key2'],
            'orderID' => $data['order_number'],
            'callback_url' => $data['callback_url'],
            'payer' => $data['request']->real_name ?? '王小明'
        ];

        $postBody["sign"] = $this->makesign([
            "timestamp" => $postBody["timestamp"],
            'amount' => strval($data['request']->amount),
            "appKey" => $data["merchant"],
            "payType" => $this->channelCodeMap[$this->channelCode],
            'orderID' => $data['order_number'],
            "payer" => $postBody['payer']
        ], $data["key"]);

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

        if ($row["success"] == true) {
            $ret = [
                'pay_url' => $row["paymentUrl"] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        }

        return ["success" => false, "msg" => $row["message"] ?? ""];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postBody = [
            "merchantCode" => $data["merchant"],
            'merchantOrderId' => $data['request']->order_number,
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }

        if ($result["code"] == "00") {
            return ["success" => true];
        }
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign([
            "type" => $data["type"],
            "amount" => $data["amount"],
            "appKey" => $data["appKey"],
            "payType" => $data["payType"],
            "orderID" => $data["orderID"],
            "status" => $data["status"],
        ], $thirdChannel->key);

        if ($sign != $data["sign"]) {
            return ["error" => "签名不正确"];
        }

        if ($data["orderID"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], ["4"])) {
            return ['success' => true];
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

        $postBody = [
            "appKey" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", false);
            if ($row["message"] == "Success") {
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
            $client = new Client([
                'timeout' => 10,
                'connect_timeout' => 10,
            ]);
            $response = $client->request($method, $url, [
                "json" => $data,
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }
            return json_decode($response->getBody(), true);
        } catch (ConnectException $e) {
            Log::error(self::class, compact('data', 'e'));
            if (str_contains($e->getMessage(), 'timed out')) {
                throw new Exception('請求超時');
            }
            throw $e;
        } catch (RequestException $e) {
            Log::error(self::class, compact('data', 'e'));
            $response = $e->getResponse();
            $responseBody = json_decode($response->getBody()->getContents(), true);
            if (!empty($responseBody)) {
                Log::error(self::class, compact('data', 'responseBody'));
                throw new Exception($responseBody['message'] ?? $responseBody['msg'] ?? $responseBody['errorMsg'] ?? '未知錯誤');
            } else {
                throw $e;
            }
        }
    }

    public function makesign($body, $key)
    {
        $signStr = urldecode($this->generateFormattedString($body) . "&$key");
        return (md5($signStr));
    }

    function generateFormattedString(array $array, array $keys = null): string
    {
        // 如果没有提供 $keys，则使用数组中的所有键
        if (is_null($keys)) {
            $keys = array_keys($array);
        }
        $formattedParts = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $array)) {
                $formattedParts[] = $array[$key];
            } else {
                $formattedParts[] = '';
            }
        }
        return implode('&', $formattedParts);
    }
}
