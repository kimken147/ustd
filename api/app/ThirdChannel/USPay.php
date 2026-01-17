<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;

class USPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'USPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://sfapi.usdotc.co/api/gw';
    public $xiafaUrl   = 'https://sfmerchant.usdotc.co/api/v1/withDraw';
    public $daifuUrl   = 'https://sfmerchant.usdotc.co/api/v1/withDraw';
    public $queryDepositUrl = 'https://sfapi.usdotc.co/api/gw';
    public $queryDaifuUrl  = 'https://sfmerchant.usdotc.co/api/v1/withDraw/query';
    public $queryBalanceUrl = 'https://sfmerchant.usdotc.co/api/v1/getBalance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 2
    ];

    public $bankMap = [

    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $param = [
            'partner'      => $data['merchant'] ?? $this->merchant,
            'type'         => '0',
            'payWayID'     => $this->channelCodeMap[$this->channelCode],
            'paymentType'  => 1,
            'amount'       => intval($data['request']->amount),
            'callbackUrl'  => $data['callback_url'],
            'orderNumber'  => $data['request']->order_number,
            'timestamp'    => now()->timestamp
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $param['payerName'] = $data['request']->real_name;
        }

        $param['sign'] = $this->makesign($param);

        $post = [
            'method' => 'recharge',
            'param'  => $param
        ];

        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->depositUrl, [
                'json' => $post
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['code']) && in_array($row['code'], [0])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'      => $row['data']['paymentInfo']
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

        $post_data = [
            'partner'       => $data['merchant'] ?? $this->merchant,
            'amount'        => intval($data['request']->amount),
            'orderNumber'   => $data['request']->order_number,
            'callbackUrl'   => $data['callback_url'],
            'currencyType'  => '0',
            'name'          => $data['request']->bank_card_holder_name,
            'account'       => $data['request']->bank_card_number,
            'bank'          => $data['request']->bank_name,
            'timestamp'     => now()->timestamp
        ];

        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->daifuUrl, [
                'json' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post_data', 'row'));

        if (isset($row['code']) && in_array($row['code'], [0])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'orderNumber'  => $data['request']->order_number,
            'partner'      => $data['merchant'] ?? $this->merchant,
            'timestamp'    => now()->timestamp
        ];
        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->queryDaifuUrl, [
                'json' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'post_data', 'response'));

        if (isset($row['code']) && in_array($row['code'], [0])) {
            if (in_array($row['data']['status'], [1])) {
                return ['success' => true, 'status' => Transaction::STATUS_PAYING];
            }
            if (in_array($row['data']['status'], [3])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($row['data']['status'], [2,4])) {
                return ['success' => true, 'status' => Transaction::STATUS_FAILED];
            }
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['orderNumber'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'],[2,3])) {
            return ['success' => true];
        }

        if ($transaction->from_id && isset($data['status']) && in_array($data['status'],[4])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'partner'   => $data['merchant'] ?? $this->merchant,
            'timestamp' => now()->timestamp
        ];

        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->queryBalanceUrl, [
                'json' => $post_data
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['code']) && in_array($result['code'], [0])) {
            $balance = $result['data']['availableBalanceRmb'];

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data) {
        ksort($data, SORT_NATURAL | SORT_FLAG_CASE);
        $signstr = '';
        foreach ($data as $k => $v) {
            if ($v != null && $v != '') {
                $signstr = $signstr . $k . '=' . $v . '&';
            }
        }
        $signstr = rtrim($signstr, "&");

        return md5($signstr . $this->key);
    }
}
