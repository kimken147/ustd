<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ThirdChannel as ThirdChannelModel;
use function Psy\debug;

class WeiZhiMo extends ThirdChannel
{
    //Log名称
    public $log_name = 'WeiZhiMo';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = '';
    public $depositUrl = 'http://47.95.199.247/pay/create';
    public $xiafaUrl = '';
    public $daifuUrl = 'http://47.95.199.247/api/trans/create_order';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = '';
    public $queryBalanceUrl = 'http://47.95.199.247/api/account/mch_balance';

    //预设商户号
    public $merchant = '';

    //预设密钥
    public $key = '';
    public $key2 = '';
    public $key3 = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "8007"
    ];

    public $bankMap = [];


    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $postBody = [
            "merchant_no" => $data["merchant"],
            'channel_code' => $data['key2'],
            "out_trade_no" => $data['order_number'],
            'total_amount' => $data['request']->amount,
            'pay_type' => 'alipay',
            'pay_method' => 'wap',
            'subject' => '付款',
            'notify_url' => $data['callback_url'],
        ];

        try {
            $response = $this->sendRequest($this->depositUrl, $postBody);
            return [
                'success' => true,
                'data' => [
                    'pay_url' => $response['pay_url'],
                ]
            ];
        } catch (\Exception $e) {
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
        return ['success' => false, 'msg' => '不支援代付'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if (isset($data['total_amount']) && $data['total_amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }
        //代收檢查狀態
        if ($data['out_trade_no'] != $transaction->order_number && $data['out_trade_no'] != $transaction->system_order_number) {
            return ['error' => '订单编号不正确'];
        }
        if (isset($data['status']) && $data['status'] == 1) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
       return 0;
    }

    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $options = [
                'json' => $data,
            ];
            $response = $client->request($method, $url, $options);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['code'] != '0') {
                throw new \Exception($row['msg']);
            }

            return $row['data'];
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBody = json_decode($response->getBody()->getContents(), true);
                $message = $responseBody['msg'] ?? $e->getMessage();
            }

            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        $signStr = urldecode(http_build_query($body));
        return hash_hmac('sha256', $signStr, $key);
    }
}
