<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Utils\BCMathUtil;
use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;
use Illuminate\Support\Carbon;

class InoPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'InoPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'http://113.31.118.50:22785/api/cashier/pc_build';
    public $xiafaUrl   = 'http://113.31.118.50:22785/api/agentpayAPI/apply';
    public $daifuUrl   = 'http://113.31.118.50:22785/api/agentpayAPI/apply';
    public $queryDepositUrl = 'http://113.31.118.50:22785/api/payAPI/queryOrder';
    public $queryDaifuUrl  = 'http://113.31.118.50:22785/api/agentpayAPI/queryOrder';
    public $queryBalanceUrl = 'http://113.31.118.50:22785/api/agentpayAPI/queryBalance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 8021
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $extra['bank'] = $this->channelCodeMap[$this->channelCode];
        $math = new BCMathUtil;
        $post = [
            'mchId'      => intval($data['merchant']) ?? intval($this->merchant),
            'appId'      => $data['key2'],
            'productId'  => $this->channelCodeMap[$this->channelCode],
            'amount'     => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分
            'currency'   => 'cny',
            'clientIp'   => $data['request']->client_ip ?? $data['client_ip'],
            'notifyUrl'  => $data['callback_url'],
            'mchOrderNo' => $data['request']->order_number,
            'subject'    => '商品名称',
            'body'       => '商品描述',
            'extra'      => json_encode($extra)
        ];

        // 未確定實名字段
        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['param2'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makesign($post);
        $params = json_encode($post);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'headers' => $postHeaders,
                'form_params' => [
                    'params' => $params
                ]
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['retCode']) && in_array($row['retCode'], ['SUCCESS'])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'   => $row['payUrl'],
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
        $math = new BCMathUtil;
        $post_data = [
            'mchId'            => intval($data['merchant']) ?? intval($this->merchant),
            'amount'           => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分
            'mchOrderNo'       => $data['request']->order_number,
            'notifyUrl'        => $data['callback_url'],
            'accountName'      => $data['request']->bank_card_holder_name,
            'accountNo'        => $data['request']->bank_card_number,
            'accountAttr'      => '0',
            'bankName'         => $data['request']->bank_name,
            'province'         => $data['request']->bank_province ?? '无',
            'city'             => $data['request']->bank_city ?? '无',
            'reqTime'          => now()->format('YmdHis'),
            'notifyUrl'  => $data['callback_url'],
            'remark'           => '代付'
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $params = json_encode($post_data);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->daifuUrl, [
                'headers' => $postHeaders,
                'form_params' => [
                    'params' => $params
                ]
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post_data', 'row'));

        if (isset($row['retCode']) && in_array($row['retCode'], ['SUCCESS'])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'mchOrderNo' => $data['request']->order_number,
            'mchId'           => intval($data['merchant']) ?? intval($this->merchant),
            'reqTime'          => now()->format('YmdHis'),
        ];
        $post_data['sign'] = $this->makesign($post_data);
        $params = json_encode($post_data);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->queryDaifuUrl, [
                'headers' => $postHeaders,
                'form_params' => [
                    'params' => $params
                ]
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'post_data', 'response'));

        if (isset($row['retCode']) && in_array($row['retCode'], ['SUCCESS'])) {
            if (in_array($row['status'], [1])) {
                return ['success' => true, 'status' => Transaction::STATUS_PAYING];
            }
            if (in_array($row['status'], [2])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($row['status'], [3])) {
                return ['success' => true, 'status' => Transaction::STATUS_FAILED];
            }

        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();
        $math = new BCMathUtil;

        if (isset($data['mchOrderNo']) && $data['mchOrderNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $math->mul($transaction->amount, 100, 0)) {   // 金額單位是分
            return ['error' => '金额不正确'];
        }

        if (isset($data['payOrderId']) && isset($data['status']) && in_array($data['status'],[2,3])) {
            return ['success' => true];
        }

        if (isset($data['agentpayOrderId']) && isset($data['status']) && in_array($data['status'],[2])) {
            return ['success' => true];
        }

        if (isset($data['agentpayOrderId']) && isset($data['status']) && in_array($data['status'],[3])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $math = new BCMathUtil;
        $post_data = [
            'mchId' => $data['merchant'],
            'reqTime' => now()->format('YmdHis'),
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $post_data = json_encode($post_data);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->queryBalanceUrl, [
                'headers' => $postHeaders,
                'form_params' => [
                    'params' => $post_data
                ]
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['retCode']) && in_array($result['retCode'],['SUCCESS'])) {
            $balance = $math->div($result['availableAgentpayBalance'], 100, 2);

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data){
        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            if ($v != null && $v != "") {
                $signstr = $signstr . $k . "=" . $v . "&";
            }
        }
        return md5($signstr . "keySign=" . $this->key . 'Apm');
    }
}
