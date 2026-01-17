<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class Boncpu extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Boncpu';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://nepay01.boncpu.cc/collect/takeorder';
    public $xiafaUrl   = 'https://nepay01.boncpu.cc/api/payfor/trans';
    public $daifuUrl   = 'https://nepay01.boncpu.cc/merchant_api/v1/orders/payment_transfer';
    public $queryDepositUrl    = 'https://nepay01.boncpu.cc/api/pay/orderquery';
    public $queryDaifuUrl  = 'https://nepay01.boncpu.cc/merchant_api/v1/orders/payment_transfer_query';
    public $queryBalanceUrl = 'https://nepay01.boncpu.cc/generic/checkbalances';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "merchantno" => $data["merchant"],
            'customerno' => $data['request']->order_number,
            'amount' => $data['request']->amount,
            "datetime" => now()->format('Y-m-d H:i:s'),
            'notifyurl' => $data['callback_url'],
            'depositname' => $data['request']->real_name ?? '王小明',
            "gateway" => $data['key2'],
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, 'msg' => $th->getMessage()];
        }

        $ret = [
            'pay_url'   => $row['checkout'] ?? '',
        ];
        return ['success' => true, 'data' => $ret];
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
        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        // if (!$this->verifySign($data, $thirdChannel->key2)) {
        //     return ["error" => "签名错误"];
        // }

        if ($data['customerno'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($data['amount'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data['state'], [2])) {
            return ['success' => true, 'resBody' => ['status' => 200]];
        }

        // //代付检查状态，失败
        // if (in_array($data->notify_type, ["payment_transfer_failed"])) {
        //     return ['fail' => '逾时'];
        // }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchantno" => $data["merchant"],
            "datetime" => now()->format('Y-m-d H:i:s'),
        ];

        try {
            $response = $this->sendRequest($data["queryBalanceUrl"], $postBody, false);
            $balance = $response["merchantbalance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            return 0;
        }
    }

    private function sendRequest($url, $data, $debug = true)
    {
        $signData = $this->makesign($data);
        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'json' => [
                    "data" => $signData
                ],
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['status'] !== 200) {
                throw new Exception($row['message'] ?? $row['msg'] ?? $row['status']);
            }

            return $row['data'];
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, [
                'data' => $data,
                'message' => $message,
            ]);
            throw $e;
        }
    }

    private function verifySign($data, $key)
    {
        $json = $data["data"];
        $pubKey = openssl_get_publickey($key);
        $verified = openssl_verify($json, base64_decode($data["signature"]), $pubKey, OPENSSL_ALGO_SHA256);
        return $verified != 0;
    }

    public function makesign($body)
    {
        // 確保所有值都是字串
        array_walk_recursive($body, function (&$value) {
            $value = (string)$value;
        });

        // 轉換為 JSON
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        // 獲取公鑰
        $pubKey = openssl_pkey_get_public($this->key);
        if (!$pubKey) {
            throw new Exception('Invalid public key');
        }

        // 獲取密鑰詳細信息
        $keyDetails = openssl_pkey_get_details($pubKey);
        $blockSize = floor($keyDetails['bits'] / 8) - 42; // OAEP padding 需要額外空間

        // 分塊加密
        $encrypted = '';
        $chunks = str_split($json, $blockSize);

        foreach ($chunks as $chunk) {
            $encryptedChunk = '';
            $success = openssl_public_encrypt(
                $chunk,
                $encryptedChunk,
                $pubKey,
                OPENSSL_PKCS1_OAEP_PADDING
            );

            if (!$success) {
                throw new Exception('Encryption failed');
            }

            $encrypted .= $encryptedChunk;
        }

        // Base64 編碼
        return base64_encode($encrypted);
    }
}
