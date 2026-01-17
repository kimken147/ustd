<?php

namespace App\ThirdChannel;

use App\Model\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Utils\BCMathUtil;
use Illuminate\Support\Arr;

class WhalePay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'WhalePay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://pay.channel-whale.com/api/collection';
    public $xiafaUrl   = 'https://pay.channel-whale.com/api/payment';
    public $daifuUrl   = 'https://pay.channel-whale.com/api/payment';
    public $queryDepositUrl = 'https://pay.channel-whale.com/api/collection/order';
    public $queryDaifuUrl  = 'https://pay.channel-whale.com/api/payment/order';
    public $queryBalanceUrl = 'https://pay.channel-whale.com/api/payment/merchant';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = [''];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $math = new BCMathUtil;
        $post = [
            'mch_id' => $data['merchant'] ?? intval($this->merchant),
            'score'      => $math->mul($data['request']->amount, 100, 0),   // 金額單位是分
            'notify_url'  => $data['callback_url'],
            'orderNo'    => $data['request']->order_number,
            'nonce_str' => Str::random(40),
            'timeStamp' => (string)now()->timestamp
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['userName'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makeDepositSign($post);
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

        if(isset($result['code']) && in_array($result['code'], [0])){
            $ret = [
                'pay_url' => $result['data']['url'],
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
            'mch_id' => $data['merchant'] ?? $this->merchant,
            'score' => $math->mul($data['request']->amount, 100, 0),   // 金額單位是分
            'orderNo'       => $data['request']->order_number,
            'notify_url'     => $data['callback_url'],
            'userName'   => $data['request']->bank_card_holder_name,
            'cardId'        => $data['request']->bank_card_number,
            'bankName' => $data['request']->bank_name,
            'subName' => $data['request']->bank_name,
            'nonce_str' => Str::random(40),
            'timeStamp' => (string)now()->timestamp
        ];

        $postData['sign'] = $this->makeDaifuSign($postData);

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

        if(isset($result['code']) && in_array($result['code'],[0])){
            return ['success' => true];
        }else{
            return ['success' => false];
        }
    }

    public function queryDaifu($data)
    {
        $postData = [
            'mch_id' => $data['merchant'] ?? $this->merchant,
            'tradeNo' => $data['request']->order_number,
            'nonce_str' => Str::random(40),
            'timeStamp' => (string)now()->timestamp
        ];
        $postData['sign'] = $this->makeDaifuSign($postData);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], ['json' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('result'));

        if(isset($result['code']) && in_array($result['code'],[0])){
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }else{
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['orderNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['score']) && $data['score'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        if (isset($data['tradeState']) && in_array($data['tradeState'],['SUCCESS'])) {
            return ['success' => true];
        }

        if (isset($data['tradeState']) && in_array($data['tradeState'],['FAIL'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        return 99999999;
    }

    public function makeDepositSign($data){
        $data = Arr::except($data, ['notify_url']);

        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            if ($v != null && $v != "") {
                $signstr = $signstr . $k . "=" . $v . "&";
            }
        }
        return md5($signstr . "key=" . $this->key);
    }

    public function makeDaifuSign($data){
        $data = Arr::except($data, ['notify_url', 'bankName', 'subName', 'cardId', 'userName', 'score']);

        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            if ($v != null && $v != "") {
                $signstr = $signstr . $k . "=" . $v . "&";
            }
        }
        return md5($signstr . "key=" . $this->key);
    }
}
