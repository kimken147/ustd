<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\Transaction;

class AAPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'AAPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://tplaf0.cn/merchant_api/v1/orders/payment';
    public $xiafaUrl   = 'https://tplaf0.cn/merchant_api/v1/orders/payment_transfer';
    public $daifuUrl   = 'https://tplaf0.cn/merchant_api/v1/orders/payment_transfer';
    public $queryDepositUrl    = 'https://tplaf0.cn/merchant_api/v1/orders/query';
    public $queryDaifuUrl  = 'https://tplaf0.cn/merchant_api/v1/orders/payment_transfer_query';
    public $queryBalanceUrl = '';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'bank'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];
        $post_data = [
            'account_name' => $data['merchant'] ?? $this->merchant,
            'merchant_order_id' => $data['request']->order_number,
            'total_amount'  => $data['request']->amount,
            'timestamp' => now()->format('c'),
            'notify_url'    => $data['callback_url'],
            'subject'   => '支付',
            'payment_method' => $this->channelCodeMap['BANK_CARD'],
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post_data['guest_real_name'] = $data['request']->real_name;
        }

        $data_json_string  = json_encode($post_data);

        Log::debug(self::class, compact('post_data'));

        $private_key = openssl_pkey_get_private($this->key);
        openssl_sign($data_json_string, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);
        $response    = $this->curl($data['url'],http_build_query(array("data" => $data_json_string,"signature" => $signature)),$headers);
        $row        = json_decode($response,true);

        Log::debug(self::class, compact('response'));

        if(isset($row['status']) && in_array($row['status'], ['init'])){
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $row['total_amount'],
                'receiver_name' => '',
                'receiver_bank_name' => '',
                'receiver_account' => '',
                'receiver_bank_branch' => '',
                'pay_url'   => $row['payment_url'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
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
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $post_data = [
            'account_name'              => $data['merchant'] ?? $this->merchant,
            'merchant_order_id'               => $data['request']->order_number,
            'total_amount'                => $data['request']->amount,
            'timestamp' => now()->format('c'),
            'notify_url'            => $data['callback_url'],
            'bank_name'             => $data['request']->bank_name,
            'bank_province_name'             => $data['request']->bank_name,
            'bank_city_name'             => $data['request']->bank_name,
            'bank_account_no'      => $data['request']->bank_card_number,
            'bank_account_name'        => $data['request']->bank_card_holder_name,
            'bank_account_type' => 'personal',
        ];

        $data_json_string  = json_encode($post_data);

        $private_key = openssl_pkey_get_private($this->key);
        openssl_sign($data_json_string, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);

        $response    = $this->curl($data['url'],http_build_query(array("data" => $data_json_string,"signature" => $signature)),$headers);
        $return_data        = json_decode($response,true);

        Log::debug(self::class, compact('post_data', 'response'));

        if(isset($return_data['status']) && in_array($return_data['status'],['init','pending_processing'])){
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $post_data = [
            'account_name'              => $data['merchant'] ?? $this->merchant,
            'merchant_order_id'               => $data['request']->order_number,
            'total_amount'          => $data['request']->amount,
        ];

        $data_json_string  = json_encode($post_data);

        $private_key = openssl_pkey_get_private($this->key);
        openssl_sign($data_json_string, $signature, $private_key, OPENSSL_ALGO_SHA256);
        $signature = base64_encode($signature);
        $response = $this->curl($data['queryDaifuUrl'],http_build_query(array("data" => $data_json_string,"signature" => $signature)),$headers);
        $return_data    = json_decode($response,true);

        Log::debug(self::class, compact('post_data', 'response'));

        if(isset($return_data['status']) && in_array($return_data['status'],['init','pending_processing'])){
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } else {
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = json_decode($request->all()['data'],true);

        if ($data['order']['merchant_order_id'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //檢查金額
        if (isset($data['order']['total_amount']) && $data['order']['total_amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        //檢查狀態
        if (isset($data['order']['status']) && in_array($data['order']['status'], ['completed'])) {
            return ['success' => true];
        }

        if (isset($data['order']['status']) && in_array($data['order']['status'], ['failed', 'denied', 'payment_expired'])) {
            return ['fail' => '失败'];
        }

        return ['error' => '未知错误'];
    }

    //目前還沒寫以後再加
    public function queryBalance($data)
    {
        return 99999999;
    }

    public function makesign($data)
    {

    }
}
