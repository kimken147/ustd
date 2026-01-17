<?php


namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\ThirdChannel as ThirdChannelModel;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class HYPay extends ThirdChannel
{
    //Log名称
    public string $log_name = 'HYPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://merchant.hy-pay.me/api/place/orders/checkout';
    public $xiafaUrl = '';
    public $daifuUrl = '';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = '';
    public $queryBalanceUrl = 'https://merchant.hy-pay.me/api/merchant/orders/query';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = '0000';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => 'card',
        Channel::CODE_ALIPAY => 'alipay',
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->merchant = $data['merchant'];
        $this->key = $data['key'];
        $postBody = [
            "mch_no" => $data["order_number"],
            'async_url' => $data['callback_url'],
            'true_name' => $data['request']->real_name ?? '王小明',
            'order_amount' => $data['request']->amount,
            'pay_channel' => $this->channelCodeMap[$this->channelCode],
        ];

        try {
            $resData = $this->sendRequest($data["url"], $postBody);
            $ret = [
                'pay_url' => $resData["url"] ?? '',
                "receiver_name" => $resData["holder"] ?? "",
                'receiver_bank_name' => $resData["bank"] ?? "",
                'receiver_account' => $resData["account"] ?? "",
                'receiver_bank_branch' => $resData["branch"] ?? "",
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
        return ["success" => false, "msg" => '無代付'];
    }

    public function queryDaifu($data)
    {
        return ["success" => false, "msg" => '無代付'];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $body = $this->decrypt($data['data'], $thirdChannel->key);


        if (
            $data["orderId"] != $transaction->order_number &&
            $data["orderId"] != $transaction->system_order_number
        ) {
            return ['error' => '支付订单编号不正确'];
        }


        //代收检查金额
        if ($body["amount"] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if ($body['status'] == 0) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $this->merchant = $data['merchant'];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], null, "GET", false);
            $balance = $row["balance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            return 0;
        }
    }

    private function sendRequest($url, $data, $method = 'POST', $debug = true)
    {
        try {
            $client = new Client();
            $options = [
                'headers' => $this->makesign($url, $data, $method),
            ];
            if ($method == 'POST') {
                $options['json'] = $data;
            } else {
                $options['query'] = $data;
            }
            $response = $client->request('POST', $url, $options);
            $row = $this->decrypt($response->getBody()->getContents(), $this->key);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if (isset($row['code']) != "0") {
                throw new \Exception($row['msg'] ?? '');
            }

            return $row['data'];
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($e instanceof RequestException) {
                if ($e->hasResponse()) {
                    $response = json_decode($e->getResponse()->getBody()->getContents(), true);
                    $msg = $response["msg"] ?? '未知錯誤';
                }
            }
            Log::error(self::class, [
                'data' => $data,
                'msg' => $msg,
            ]);
            throw $e;
        }
    }

    public function makesign($url, $body, $key)
    {
        $body === null ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = time();
        $sign = hash_hmac('sha256', $url + $timestamp + $body, $key);
        return [
            'ACCESS-KEY' => $this->merchant,
            'ACCESS-TIMESTAMP' => $timestamp,
            'ACCESS-SIGN' => $sign,
        ];
    }

    public function decrypt($encryptedData, $key)
    {
        $decoded = base64_decode($encryptedData);
        $decrypted = openssl_decrypt($decoded, 'AES-128-CBC', $key, OPENSSL_RAW_DATA, $key);
        return json_decode($decrypted, true);
    }
}
