<?php

namespace App\ThirdChannel;

use App\Model\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Utils\BCMathUtil;

class TripleX extends ThirdChannel
{
    //Log名称
    public $log_name   = 'TripleX';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://chyguopay.com/api/order/receive/createorder';
    public $xiafaUrl = 'https://chyguopay.com/api/order/payment/createorder';
    public $daifuUrl = 'https://chyguopay.com/api/order/payment/createorder';
    public $queryDepositUrl = 'https://chyguopay.com/api/order/receive/inquireorder';
    public $queryDaifuUrl = 'https://chyguopay.com/api/order/payment/inquireorder';
    public $queryBalanceUrl = 'https://chyguopay.com/api/order/payment/searchmoney';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'SUCCESS';

    //白名单
    public $whiteIP = [];

    public $channelCodeMap = [
        'BANK_CARD' => 'CopyToBank'
    ];

    public $bankMap = [
        '工商银行' => 1,
        '中国工商银行' => 1,
        '建设银行' => 2,
        '中国建设银行' => 2,
        '中国银行' => 3,
        '农业银行' => 4,
        '中国农业银行' => 4,
        '交通银行' => 5,
        '邮储银行' => 6,
        '邮政储蓄银行' => 6,
        '中国邮政储蓄银行' => 6,
        '兴业银行' => 7,
        '中信银行' => 8,
        '浦发银行' => 9,
        '上海浦东发展银行' => 9,
        '民生银行' => 10,
        '中国民生银行' => 10,
        '平安银行' => 11,
        '光大银行' => 12,
        '中国光大银行' => 12,
        '华夏银行' => 13,
        '广发银行' => 14,
        '北京银行' => 15,
        '江苏银行' => 16,
        '上海银行' => 17,
        '浙商银行' => 18,
        '微众银行' => 19,
        '网商银行' => 20,
        '新网银行' => 21,
        '苏宁银行' => 22,
        '恒丰银行' => 23,
        '国家开发银行' => 24,
        '温州银行' => 25,
        '宁波银行' => 26,
        '浙江稠州银行' => 27,
        '台州银行' => 28,
        '浙江民泰银行' => 29,
        '农村商业银行' => 30,
        '徽商银行' => 31,
        '招商银行' => 32,
        '昆仑银行' => 33,
        '哈尔滨银行' => 34,
        '南京银行' => 35,
        '农村信用社' => 36,
        '中原银行' => 37,
        '长安银行' => 38,
        '西安银行' => 39,
        '渤海银行' => 40,
        '陕西信合' => 41,
        '宁夏银行' => 42,
        '广西北部湾银行' => 43,
        '长沙银行' => 44,
        '贵阳银行' => 45,
        '东亚银行' => 46,
        '杭州银行' => 47,
        '海峡银行' => 48,
        '福建农信' => 49,
        '沧州银行' => 50,
        '锦州银行' => 51,
        '河北银行' => 52,
        '贵州银行' => 53,
        '鄞州银行' => 54,
        '新华银行' => 55,
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $postBody = [
            'types_of' => 'fq',
            'merchant_ID' => $data['merchant'] ?? $this->merchant,
            'merchant_order' => $data['request']->order_number,
            'pay_type' => $this->channelCodeMap[$this->channelCode],
            'amount' => $data['request']->amount,
            'callback_url' => $data['callback_url'],
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $postBody['full_name'] = $data['request']->real_name;
        }

        $sign = $this->makesign($postBody, $this->key);

        $postBody['Encrypted'] = $sign;
        $postBody['merchant_ID'] = base64_encode($postBody['merchant_ID']);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('postBody', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('postBody', 'row'));

        if (isset($row['code']) && in_array($row['code'], [1])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'receiver_name' => $row['data']['account']['name'],
                'receiver_bank_name' => $row['data']['account']['bank'],
                'receiver_account' => $row['data']['account']['card'],
                'receiver_bank_branch' => $row['data']['account']['branch'],
                'pay_url'   => $row['data']['url'],
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

        if (!isset($this->bankMap[$data['request']->bank_name])) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $postBody = [
            'types_of' => 'fq',
            'merchant_ID' => $data['merchant'] ?? $this->merchant,
            'merchant_order' => $data['request']->order_number,
            'money' => $data['request']->amount,
            'bank_type' => 1,
            'bank_ID' => $this->bankMap[$data['request']->bank_name],
            'bank_branch' => $data['request']->bank_name,
            'account' => $data['request']->bank_card_number,
            'name' => $data['request']->bank_card_holder_name,
            'callback' => $data['callback_url']
        ];

        $sign = $this->makesign($postBody);

        $postBody['Encrypted'] = $sign;
        $postBody['merchant_ID'] = base64_encode($postBody['merchant_ID']);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('postBody', 'message'));
            return ['success' => true];
        }

        Log::debug(self::class, compact('postBody', 'row'));

        if (isset($row['code']) && in_array($row['code'],[1])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postBody = [
            'merchant_order' => $data['request']->order_number,
            'merchant_ID' => $data['merchant'] ?? $this->merchant,
            'types_of' => 'fq'
        ];

        $sign = $this->makesign($postBody);

        $postBody['Encrypted'] = $sign;
        $postBody['merchant_ID'] = base64_encode($postBody['merchant_ID']);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
                'json' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('postBody', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('postBody', 'response'));

        if (isset($row['code']) && in_array($row['code'],[1])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['merchant_order'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收檢查金額
        if (isset($data['money']) && $data['money'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        //檢查狀態，成功
        if (isset($data['status']) && in_array($data['status'], [1,'Order Completed'])) {
            $this->success = $data['auth_content'];
            return ['success' => true];
        }

        //檢查狀態，失敗
        if (isset($data['status']) && in_array($data['status'], [2, 3])) {
            $this->success = $data['auth_content'];
            return ['fail' => '駁回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        return 99999999;
    }

    public function makesign($data){
        ksort($data);
        $signstr = json_encode($data, JSON_UNESCAPED_SLASHES) . '->MERCHANTS_KEY:' . $this->key;
        return md5(hash('ripemd160', $signstr));
    }
}
