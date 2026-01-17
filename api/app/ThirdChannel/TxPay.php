<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;
use Illuminate\Support\Arr;

class TxPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'TxPay';
    public $type    = 2; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'http://walletfat-api-gateway.4771m1.com/api/merchant/deposit/v2';
    public $xiafaUrl   = '';
    public $daifuUrl   = '';
    public $queryDepositUrl = '';
    public $queryDaifuUrl  = '';
    public $queryBalanceUrl = 'http://walletfat-api-gateway.4771m1.com/api/merchant/getMerchantBalanceInfo/v2';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = '{ "code":200, "msg":"成功" }';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 10000
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $post = [
            'merchantNo' => $data['merchant'],
            'userName' => Str::random(15),
            'merchantOrderId' => $data['request']->order_number,
            'deviceType' => 9,
            'payAmount' => $data['request']->amount,
            'payTypeId' => $this->channelCodeMap[$this->channelCode],
            'depositNotifyUrl' => $data['callback_url'],
            'loginIp' => $data['request']->client_ip ?? $data['client_ip'],
            'depositName' =>  $data['request']->real_name
        ];

        // 未確定實名字段
        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['acname'] = $data['request']->real_name;
        }

        $post['sign'] = md5("merchantNo={$post['merchantNo']}&merchantOrderId={$post['merchantOrderId']}&userName={$post['userName']}&deviceType={$post['deviceType']}&key={$data['key']}");
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'headers' => $postHeaders,
                'form_params' => $post
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['code']) && in_array($row['code'], [200])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'   => data_get($row, 'data.url'),
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ['success' => false];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true,'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $content = [
            'orderno'   => $data['request']->order_number,
            'date'      => now()->format('YmdHis'),
            'amount'    => $data['request']->amount,
            'account'   => $data['request']->bank_card_number,
            'name'      => $data['request']->bank_card_holder_name,
            'bank'      => $data['request']->bank_name,
            'subbranch' => $data['request']->bank_name
        ];
        $post_data = [
            'userid'       => $data['merchant'],
            'action'       => 'withdraw',
            'notifyurl'    => $data['callback_url'],
            'notifystyle'  => 2,
            'content'      => json_encode([$content]),
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'headers' => $postHeaders,
                'form_params' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post_data', 'row'));

        if (isset($row['status']) && in_array($row['status'], [1])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'orderno'  => $data['request']->order_number,
            'userid'   => $data['merchant'],
            'action'   => 'withdrawquery',
        ];
        $post_data['sign'] = $this->makesign($post_data);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'headers' => $postHeaders,
                'form_params' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'post_data', 'response'));

        if (isset($row['status']) && in_array($row['status'], [1])) {
            if (in_array($row['content']['orderstatus'], ['0', '2'])) {
                return ['success' => true, 'status' => Transaction::STATUS_PAYING];
            }
            if (in_array($row['content']['orderstatus'], ['1'])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($row['content']['orderstatus'], ['3'])) {
                return ['success' => true, 'status' => Transaction::STATUS_FAILED];
            }

        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if (isset($data['merchantOrderId']) && $data['merchantOrderId'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['orderAmount']) && $data['orderAmount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['orderStatus']) && in_array($data['orderStatus'],[1])) {
            return ['success' => true];
        }

        if (isset($data['status']) && in_array($data['status'],['0'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'userid' => $data['merchant'],
            'date'   => now()->format('YmdHis'),
            'action' => 'balance'
        ];

        $post_data['sign'] = md5("merchantNo={$data['merchant']}&key={$data['key']}");
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryBalanceUrl'], [
                'headers' => $postHeaders,
                'form_params' => $post_data
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['status']) && in_array($result['status'],[1])) {
            $balance = $result['money'];

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data) {
        return '';
    }
}
