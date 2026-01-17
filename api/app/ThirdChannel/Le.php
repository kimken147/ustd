<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class Le extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Le';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://zhifu.meiannaisi.com/api/deposits';
    public $xiafaUrl   = '';
    public $daifuUrl   = '';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = '';
    public $queryBalanceUrl = 'https://zhifu.meiannaisi.com/api/me';

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
            'amount' => strval($data['request']->amount),
            "channel" => $data['key2'] ?? $this->channelCodeMap[$this->channelCode],
            "merchant_number" => $data["merchant"],
            'order_number' => $data['request']->order_number,
            'notify_url' => $data['callback_url'],
            'real_name' => $data['request']->real_name ?? 'VIP会员',
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            $ret = [
                'pay_url'   => $row["redirect_url"] ?? '',
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
        return ["success" => false, 'msg' => '通知不支援代付'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data["order_number"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["order_amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], ['2', '6']) && in_array($transaction->type, [1])) {
            return ['success' => true];
        }

        // //代付检查状态，失败
        // if (in_array($data["status"], [4])) {
        //     return ['fail' => '逾时'];
        // }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "merchant_number" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, 'GET', false);
            $balance = $row['data']["balance"];
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

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $options = $method === "GET"
                ? ["query" => $data]  // GET 方法使用 query 參數
                : ["form_params" => $data];  // POST 方法使用 json 參數

            $response = $client->request($method, $url, $options);

            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            return $row;
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response) {
                $response = json_decode($response->getBody()->getContents());
                $message = $response->message ?? "";
            } else {
                $message = $e->getMessage();
            }

            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = (http_build_query($body) . "&secret_key=" . $key);
        return (md5($signStr));
    }
}
