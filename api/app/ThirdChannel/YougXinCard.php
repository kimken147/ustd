<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class YougXinCard extends ThirdChannel
{
    //Log名称
    public $log_name = 'YougXinCard';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://ucpays.net/api/v2/deposits';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://ucpays.net/withdraw/order';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://ucpays.net/withdraw/order/query';
    public $queryBalanceUrl = 'https://ucpays.net/api/me';

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
        Channel::CODE_BANK_CARD => "BANK_CARD",
        Channel::CODE_QR_ALIPAY => 'QR_ALIPAY',
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "merchant_number" => $data["merchant"],
            'order_number' => $data['request']->order_number,
            "channel" => $data["key2"] ?? $this->channelCodeMap[$this->channelCode],
            'amount' => strval($data['request']->amount),
            'notify_url' => $data['callback_url'],
            "client_ip" => $data['request']->client_ip ?? $data['client_ip'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['real_name'] = $data['request']->real_name;
        } else {
            $postBody['real_name'] = '王小明';
        }

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            $info = $row['data'];
            $ret = [
                'pay_url' => $info["redirect_url"] ?? '',
                'receiver_account' => $info["receiver_account"] ?? '',
                'receiver_bank_name' => $info["receiver_bank_name"] ?? '',
                'receiver_name' => $info["receiver_name"] ?? '',
                'receiver_bank_branch' => ''
            ];
            return ['success' => true, 'data' => $ret];
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $row["message"] ?? $th->getMessage()];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        return ["success" => false, 'msg' => '不支持代付'];
    }

    public function queryDaifu($data)
    {
        return ["success" => false, 'msg' => '不支持代付'];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($sign != $data["sign"]) {
            return ["error" => "签名不正确"];
        }

        if ($data["order_number"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["order_amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], [2, 6])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], [4, 5])) {
            return ['fail' => '逾时', "statusCode" => 200];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "merchant_number" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", false);
            $balance = $row["data"]["available_balance"];
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

            if ($row['http_status_code'] != 200 || $row['http_status_code'] !== 201) {
                throw new \Exception($row['message']);
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
        $signStr = (http_build_query($body) . "&secret_key=$key");
        return md5($signStr);
    }
}
