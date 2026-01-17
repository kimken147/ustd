<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class Yihua extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Flaresec';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://www.66pay.art/api/transactions/initiate';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://dawn.flaresec.com/order/create/';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://dawn.flaresec.com//order/query/';
    public $queryBalanceUrl = 'https://www.66pay.art/api/users/:memberId/balance';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'OK';

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
            'memberId' => $data['merchant'],
            'orderId' => $data['request']->order_number,
            'channelCode' => $data['key2'] ?? '9699',
            'notifyUrl' => $data['callback_url'],
            'returnUrl' => 'www.baidu.com',
            'amount' => $this->bcMathUtil->mul($data['request']->amount, 100, 0),
            'responseType' => 'json',
            'productName' => '充值',
            'productNumber' => '1',
            'productDesc' => '消费',
            'productUrl' => 'www.baidu.com',
        ];

        try {
            $res = $this->sendRequest($data['url'], $postBody);
            return [
                'success' => true,
                'data' => [
                    'pay_url' => $res['url']
                ]
            ];
        } catch (Exception $e) {
            Log::error(__CLASS__ . ":" . __FUNCTION__ . ":" . $e->getMessage());
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
       return ['success' => true, 'msg' => '不支援代付'];
    }

    public function queryDaifu($data)
    {
       return ['success' => false, 'msg' => '不支援代付'];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data["transactionId"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $this->bcMathUtil->mul($transaction->amount, 100, 2)) {
            return ['error' => '代收金额不正确'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "timestamp" => now()->timestamp,
            'nonce' => Str::random(10),
        ];

        $url = str_replace(':memberId', $data['merchant'], $data['queryBalanceUrl']);
        try {
            $res = $this->sendRequest($url, $postBody, 'GET');
            $balance = $this->bcMathUtil->div($res["balance"], 100, 2);
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            Log::error(self::class, compact('postBody', 'message'));
            return 0;
        }
    }

    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $data["signature"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $options = [];
            if ($method === 'POST') {
                $options['form_params'] = $data;
            }
            else if ($method === 'GET') {
                $options['query'] = $data;
            }
            $response = $client->request($method, $url, $options);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['responseCode'] != 200) {
                throw new Exception($row['responseDescription'], 1);
            }

            return $row;
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message = json_decode($response->getBody()->getContents(), true);
            $message = $message['responseDescription'] ?? $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["signature"]);
        $signStr = urldecode(http_build_query($body) . "&key=$key");
        return strtoupper(md5($signStr));
    }
}
