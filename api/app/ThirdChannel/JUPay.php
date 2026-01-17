<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ThirdChannel as ThirdChannelModel;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class JUPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'JUPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'http://api.jupay.top';
    public $xiafaUrl   = 'http://api.jupay.pw/payforcustom.aspx';
    public $daifuUrl   = 'http://api.jupay.pw/payforcustom.aspx';
    public $queryDepositUrl = 'http://api.jupay.pw/search.aspx';
    public $queryDaifuUrl  = 'http://api.jupay.pw/payforresearch.aspx';
    public $queryBalanceUrl = 'http://api.jupay.pw/getbalance.aspx';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'SUCCESS';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'YLNET'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $post = [
            'merchant_id'  => $data['merchant'],
            'orderid'      => $data['request']->order_number,
            'paytype'      => $this->channelCodeMap[$this->channelCode],
            'notifyurl'    => $data['callback_url'],
            'callbackurl'  => '',
            'userip'       => $data['request']->client_ip ?? $data['client_ip'],
            'money'        => $data['request']->amount
        ];

        // 未確定實名字段
        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['realname'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makesign($post, 'deposit');
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->depositUrl, [
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

        if (isset($row['status']) && in_array($row['status'], [1])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'   => $row['data']['url'],
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
            'corderid'      => $data['request']->order_number,
            'money'         => $data['request']->amount,
            'bankcode'      => $data['request']->bank_card_number,
            'bankusername'  => $data['request']->bank_card_holder_name,
            'bankname'      => $data['request']->bank_name,
            'bankaddress'   => $data['request']->bank_name
        ];
        $post_data = [
            'merchant_id'  => $data['merchant'],
            'userip'       => '168.168.168.168',
            'notifyurl'    => $data['callback_url'],
            'data'         => json_encode([$content]),
        ];

        $post_data['sign'] = $this->makesign($post_data, 'daifu');
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
            'corderid'    => $data['request']->order_number,
            'merchant_id' => $data['merchant']
        ];
        $post_data['sign'] = $this->makesign($post_data, 'queryDaifu');
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
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
            if (in_array($row['data']['status'], ['1'])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($row['data']['status'], ['2'])) {
                return ['success' => true, 'status' => Transaction::STATUS_FAILED];
            }
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if (isset($data['orderid']) && $data['orderid'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['money']) && $data['money'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'],['1'])) {
            return ['success' => true];
        }

        if (isset($data['status']) && in_array($data['status'],['2'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'merchant_id' => $data['merchant']
        ];

        $post_data['sign'] = $this->makesign($post_data, 'balance');
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
            $balance = $result['data']['account'];

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data, $type) {

        if ($type == 'balance') {
            $signstr = $data['merchant_id'];
        }

        if ($type == 'daifu') {
            $signstr = $data['merchant_id'].$data['notifyurl'].$data['userip'].$data['data'];
        }

        if ($type == 'queryDaifu') {
            $signstr = $data['merchant_id'].$data['corderid'];
        }

        if ($type == 'deposit') {
            $signstr = $data['merchant_id'].$data['orderid'].$data['paytype'].$data['notifyurl'].$data['callbackurl'].$data['money'];
        }

        $signstr = $signstr . $this->key;

        return md5($signstr);
    }
}
