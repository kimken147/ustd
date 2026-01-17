<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class BoncpuV3 extends ThirdChannel
{
    //Log名称
    public $log_name = 'BoncpuV3';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://nepay01.boncpu.cc/collect/takeorder';
    public $xiafaUrl = 'https://nepay01.boncpu.cc/api/payfor/trans';
    public $daifuUrl = 'https://merchant.boncpu.cc/{merchantNo}/pay/takeorder';
    public $queryDepositUrl = 'https://merchant.boncpu.cc/{merchantNo}/pay/checkorder';
    public $queryDaifuUrl = 'https://merchant.boncpu.cc/{merchantNo}/pay/checkorder';
    public $queryBalanceUrl = 'https://merchant.boncpu.cc/{merchantNo}/generic/checkbalances';
    private $gatewayParamsUrl = 'https://merchant.boncpu.cc/{merchantNo}/generic/checkgatewayfield';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        return ['success' => false, 'msg' => '不支援代收'];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $url = str_replace('{merchantNo}', $data['merchant'], $data['url']);
        $this->key = $data['key'];
        $body = [
            'pur_merchantno' => $data['merchant'],
            'pur_amount' => $data['request']->amount,
            'pur_datetime' => now()->format('Y-m-d H:i:s'),
            'pur_notifyurl' => $data['callback_url'],
            'pur_accountname' => $data['request']->bank_card_holder_name,
            'pur_gateway' => $data['key2'],
            'pur_customerno' => $data['order_number'],
            '姓名' => $data['request']->bank_card_holder_name,
            '银行名称' => $data['request']->bank_name,
            '卡号' => $data['request']->bank_card_number,
        ];

        try {
            $res = $this->sendRequest($url, $body);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function queryDaifu($data)
    {
        $url = str_replace('{merchantNo}', $data['merchant'], $data['queryDaifuUrl']);
        $this->key = $data['key'];
        $body = [
            'pur_merchantno' => $data['merchant'],
            'pur_customerno' => $data['request']->order_number,
            'pur_datetime' => now()->format('Y-m-d H:i:s'),
            'pur_gateway' => $data['key2'],
        ];

        try {
            $res = $this->sendRequest($url, $body);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        // if (!$this->verifySign($data, $thirdChannel->key2)) {
        //     return ["error" => "签名错误"];
        // }

        if ($data['pur_customerno'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($data['pur_amount'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data['pur_state'] == 3) {
            return ['success' => true, 'resBody' => ['status' => 200]];
        }

        if ($data['pur_state'] == 4) {
            return ['fail' => '逾時', 'resBody' => ['status' => 200]];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $url = str_replace('{merchantNo}', $data['merchant'], $data['queryBalanceUrl']);
        $postBody = [
            "merchantno" => $data["merchant"],
            "datetime" => now()->format('Y-m-d H:i:s'),
        ];

        try {
            $response = $this->sendRequest($url, $postBody, false);
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
