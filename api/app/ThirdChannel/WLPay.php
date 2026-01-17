<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Models\ThirdChannel as ThirdChannelModel;
use App\Models\Transaction;


class WLPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'WLPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://30678.vip/api/pay/pay';
    public $xiafaUrl   = 'https://30678.vip/api/pay/behalf';
    public $daifuUrl   = 'https://30678.vip/api/pay/behalf';
    public $queryDepositUrl = 'https://30678.vip/api/pay/get_pay';
    public $queryDaifuUrl  = 'https://30678.vip/api/pay/get_behalf';
    public $queryBalanceUrl = 'https://30678.vip/api/pay/get_money';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'ok';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'B2C'
    ];

    public $bankMap = [

    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $post = [
            'time'         => now()->format('Y-m-d H:i:s'),
            'username'     => intval($data['merchant']) ?? intval($this->merchant),
            'api'          => 1,
            'code'         => $this->channelCodeMap[$this->channelCode],
            'money'        => intval($data['request']->amount),
            'notice_url'   => $data['callback_url'],
            'lsh'          => $data['request']->order_number
        ];

        $post['sig'] = $this->makesign($post);

        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
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
                'pay_url'      => $row['url']
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
            'time'       => now()->format('Y-m-d H:i:s'),
            'username'   => intval($data['merchant']) ?? intval($this->merchant),
            'money'      => intval($data['request']->amount),
            'lsh'        => $data['request']->order_number,
            'notice_url' => $data['callback_url'],
            'api'        => 1,
            'bank_name'  => $data['request']->bank_card_holder_name,
            'bank_nub'   => $data['request']->bank_card_number
        ];

        $post_data['sig'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
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
            'time'         => now()->format('Y-m-d H:i:s'),
            'lsh'          => $data['request']->order_number,
            'username'     => intval($data['merchant']) ?? intval($this->merchant)
        ];
        $post_data['sig'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
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

        if ($data['lsh'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['money']) && $data['money'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'],[2])) {
            return ['success' => true];
        }

        if (isset($data['status']) && in_array($data['status'],[3])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'time'     => now()->format('Y-m-d H:i:s'),
            'username' => intval($data['merchant']) ?? intval($this->merchant)
        ];

        $post_data['sig'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryBalanceUrl'], [
                'json' => $post_data
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['code']) && in_array($result['code'],[0])) {
            $balance = $result['money'];

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data) {
        $data['key'] = $this->key;
        ksort($data);
        $signstr = [];
        foreach (array_filter($data) as $k => $v) {
            if ($v != null && $v != "") {
                $signstr[] = $k . "=" . $v;
            }
        }
        return md5(implode('&', $signstr));
    }
}
