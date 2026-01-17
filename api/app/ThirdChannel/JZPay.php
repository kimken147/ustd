<?php

namespace App\ThirdChannel;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Utils\BCMathUtil;
use Illuminate\Support\Arr;
use App\Models\ThirdChannel as ThirdChannelModel;

class JZPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'JZPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://mxpays.com/gateway/api/v1/payments';
    public $xiafaUrl   = 'https://mxpays.com/gateway/api/v2/payouts';
    public $daifuUrl   = 'https://mxpays.com/gateway/api/v2/payouts';
    public $queryDepositUrl = 'https://mxpays.com/gateway/api/v1/payments';
    public $queryDaifuUrl  = 'https://mxpays.com/gateway/api/v1/payouts';
    public $queryBalanceUrl = 'https://mxpays.com/gateway/api/v1/platforms/balance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = '{"error_code": "0000"}';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'SVC0001',
    ];

    public $bankMap = [

    ];
    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $math = new BCMathUtil;
        $post = [
            'platform_id' => $data['merchant'] ?? $this->merchant,
            'amount' => $math->mul($data['request']->amount, 100, 0),   // 金額單位是分
            'notify_url' => $data['callback_url'],
            'payment_cl_id' => $data['request']->order_number,
            'service_id' => $this->channelCodeMap[$this->channelCode],
            'request_time' => now()->timestamp
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['name'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makeSign($post);
        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['json' => $post]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if(isset($result['error_code']) && in_array($result['error_code'], ['0000'])){
            $ret = [
                'pay_url' => $result['data']['link'],
            ];
        }else{
            return ['success' => false];
        }
        return ['success' => true,'data' => $ret];
    }

    public function queryDeposit($data)
    {
        return ['success' => true,'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];

        $math = new BCMathUtil;
        $postData = [
            'platform_id' => $data['merchant'] ?? $this->merchant,
            'amount' => $math->mul($data['request']->amount, 100, 0),   // 金額單位是分
            'payout_cl_id' => $data['request']->order_number,
            'notify_url' => $data['callback_url'],
            'name' => $data['request']->bank_card_holder_name,
            'number' => $data['request']->bank_card_number,
            'service_id' => 'SVC0004',
            'request_time' => now()->timestamp
        ];

        $postData['sign'] = $this->makeSign($postData);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['json' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if(isset($result['error_code']) && in_array($result['error_code'], ['0000'])){
            return ['success' => true];
        }else{
            return ['success' => false];
        }
        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $postData = [
            'payout_cl_id' => $data['request']->order_number,
        ];

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $data['queryDaifuUrl'], ['headers' => ['Authorization' => $data['key2']], 'query' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if (isset($result['data']) && count($result['data']) > 0){
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } else{
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
        return ['success' => false];
    }

    public function callback($request, $transaction)
    {
        $math = new BCMathUtil;

        $data = $request->all();


        if (isset($data['amount']) && $data['amount'] != $math->mul($transaction->amount, 100, 0)) {
            return ['error' => '代收金额不正确'];
        }

        if ($transaction->to_id) { //代收

            if ($data['payment_cl_id'] != $transaction->order_number) {
                return ['error' => '订单编号不正确'];
            }

            if (isset($data['real_amount']) && $data['real_amount'] != $math->mul($transaction->amount, 100, 0)) {
                return ['error' => '代收金额不正确'];
            }

            if (isset($data['status']) && in_array($data['status'], [2])) {
                return ['success' => true];
            }

            if (isset($data['status']) && in_array($data['status'], [3, 4])) {
                return ['fail' => '驳回'];
            }
        }

        if ($transaction->from_id) { //代付
            if ($data['payout_cl_id'] != $transaction->order_number) {
                return ['error' => '订单编号不正确'];
            }

            if (isset($data['amount']) && $data['amount'] != $math->mul($transaction->amount, 100, 0)) {
                return ['error' => '代付金额不正确'];
            }

            if (isset($data['status']) && in_array($data['status'], [3])) {
                return ['success' => true];
            }

            if (isset($data['status']) && in_array($data['status'], [4, 5])) {
                return ['fail' => '驳回'];
            }
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $math = new BCMathUtil;

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $data['queryBalanceUrl'], ['headers' => ['Authorization' => $data['key2']]]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        Log::debug(self::class, compact('data', 'result'));

        if (isset($result['error_code']) && in_array($result['error_code'],['0000'])) {
            $balance = $math->div($result['data']['total_balance'], 100, 2);

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makeSign($data){
        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            if ($v != null && $v != '') {
                $signstr = $signstr . $k . '=' . $v . '&';
            }
        }
        return md5($signstr . $this->key);
    }
}
