<?php

namespace App\ThirdChannel;

use App\Http\Resources\Admin\Channel;
use App\Model\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;

class Fubao extends ThirdChannel
{
    //Log名称
    public $log_name = 'HaoHui';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://xyz.bingopay888.com/api/v2/pay_request';
    public $xiafaUrl = 'https://xyz.bingopay888.com/api/withdraw/request';
    public $daifuUrl = 'https://xyz.bingopay888.com/api/withdraw/request';
    public $queryDepositUrl = 'https://xyz.bingopay888.com/api/pay_status';
    public $queryDaifuUrl = 'https://xyz.bingopay888.com/api/withdraw/status';
    public $queryBalanceUrl = 'https://xyz.bingopay888.com/api/withdraw/cash_balance';

    //预设商户号
    public $merchant = '';

    //预设密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = '888888';

    //白名单
    public $whiteIP = ['13.209.119.152'];

    public $channelCodeMap = [
        'BANK_CARD' => 'bankcard',
        \App\Model\Channel::CODE_QR_ALIPAY => 'aliali',
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $this->key,
        ];

        $post = [
            'account' => $data['merchant'] ?? $this->merchant,
            'payType' => $data['key3'] ?? $this->channelCodeMap['BANK_CARD'],
            'payMoney' => $data['request']->amount,
            'notifyURL' => $data['callback_url'],
            'orderNo' => $data['request']->order_number,
            'ip' => $data['request']->client_ip ?? $data['client_ip'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['playerName'] = $data['request']->real_name;
        }

        $post_data = http_build_query($post);
        $response = $this->curl($data['url'], $post_data, $headers);
        $row = json_decode($response, true);

        Log::debug(self::class, compact('post_data', 'response'));

        if (isset($row['retCode']) && in_array($row['retCode'], [0])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $row['realCharge'],
                'receiver_name' => $row['bankHolder'] ?? '',
                'receiver_bank_name' => $row['bankName'] ?? '',
                'receiver_account' => $row['bankNumber'] ?? '',
                'receiver_bank_branch' => $row['bankBranch'] ?? '',
                'pay_url' => $row['redirectURL'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } else {
            return ['success' => false, 'msg' => $row['retMsg'] ?? ''];
        }
        return ['success' => true, 'data' => $ret];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $this->key2 = $data['key2'];
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $this->key,
        ];

        $post_data = [
            'account' => $data['merchant'] ?? $this->merchant,
            'orderNo' => $data['request']->order_number,
            'amount' => $data['request']->amount,
            'bankName' => $data['request']->bank_name,
            'bankNumber' => $data['request']->bank_card_number,
            'bankHolder' => $data['request']->bank_card_holder_name,
            'notifyURL' => $data['callback_url'],
            'nonceStr' => 'wmh' . $data['request']->order_number,
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $response = $this->curl($data['url'], http_build_query($post_data), $headers);
        $return_data = json_decode($response, true);

        Log::debug(self::class, compact('post_data', 'response'));

        if (isset($return_data['retCode']) && in_array($return_data['retCode'], [0])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $this->key2 = $data['key2'];
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $this->key,
        ];

        $post_data = [
            'account' => $data['merchant'] ?? $this->merchant,
            'orderNo' => $data['request']->order_number,
            'nonceStr' => 'wmh' . $data['request']->order_number,
        ];
        $post_data['sign'] = $this->makesign($post_data);
        $response = $this->curl($data['queryDaifuUrl'], http_build_query($post_data), $headers);
        $return_data = json_decode($response, true);

        Log::debug(self::class, compact('post_data', 'response'));

        if (isset($return_data['status']) && in_array($return_data['status'], [0, 1])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } else {
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

        //代收檢查金額
        if (isset($data['realCharge']) && $data['realCharge'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代付檢查金額
        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '代付金额不正确'];
        }

        //代收檢查狀態
        if (isset($data['payStatus']) && in_array($data['payStatus'], ['success'])) {
            return ['success' => true];
        }

        //代付檢查狀態，成功
        if (isset($data['status']) && in_array($data['status'], [1])) {
            return ['success' => true];
        }

        //代付檢查狀態，失敗
        if (isset($data['status']) && in_array($data['status'], [2])) {
            return ['fail' => '逾時'];
        }

        return ['error' => '未知错误'];
    }

    //目前還沒寫以後再加
    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $this->key2 = $data['key2'];
        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer ' . $this->key,
        ];

        $post_data = [
            'account' => $data['merchant'] ?? $this->merchant,
        ];
        $post_data['sign'] = $this->makesign($post_data);
        $response = $this->curl($data['queryBalanceUrl'], http_build_query($post_data), $headers);
        $return_data = json_decode($response, true);

        Log::debug(self::class, compact('post_data', 'response'));

        if (isset($return_data['retCode']) && in_array($return_data['retCode'], [0])) {
            $balance = $return_data['balance'];
            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);
            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data)
    {
        ksort($data);
        $data = http_build_query($data);
        $strSign = $data . "&key={$this->key2}";
        $sign = strtoupper(hash_hmac("sha256", $strSign, $this->key2));
        return $sign;
    }
}
