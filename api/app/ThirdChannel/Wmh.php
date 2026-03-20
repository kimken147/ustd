<?php

namespace App\ThirdChannel;

use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;

class Wmh extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Wmh';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api.wmh168.com/api/v1/merchant-api/deposits';
    public $xiafaUrl   = 'https://api.wmh168.com/api/v1/merchant-api/payouts';
    public $daifuUrl   = 'https://api.wmh168.com/api/v1/merchant-api/agency-payouts';
    public $queryDepositUrl = '';
    public $queryDaifuUrl  = 'https://api.wmh168.com/api/v1/merchant-api/payouts/query';
    public $queryBalanceUrl = 'https://api.wmh168.com/api/v1/merchant-api/balance';

    //预设商户号
    public $merchant    = 'sgameboydf';

    //预设密钥
    public $key         = 'gMJXeAE507dbJJaK8yrPfsSmMHHVgkn3';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['52.74.156.139', '3.1.207.157'];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $post = [
            'merchant_id' => $data['merchant'] ?? $this->merchant,
            'channel' => $data["key2"] ?? $this->channelCode,
            'amount' => $data['request']->amount,
            'callback_url' => $data['callback_url'],
            'out_trade_no' => $data['request']->order_number,
            'client_ip'  => $data['request']->client_ip ?? $data['client_ip'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['payer_name'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makesign($post);
        $client = new Client();

        try {
            $response = $client->request('POST', $data['url'], [
                'json' => $post,
            ]);

            $resBody = json_decode($response->getBody()->getContents(), true);
            Log::debug(self::class, compact('post', 'resBody'));

            if (isset($resBody['data']['status']) && in_array($resBody['data']['status'], [0, 1])) {
                $ret = [
                    'receiver_name' => $resBody['data']['payee_name'] ?? '',
                    'receiver_bank_name' => $resBody['data']['bank_name'] ?? '',
                    'receiver_account' => $resBody['data']['pay_address'] ?? '',
                    'receiver_bank_branch' => $resBody['data']['bank_branch'] ?? '',
                    'pay_url' => $resBody['data']['cashier_url'],
                ];
                return ['success' => true, 'data' => $ret];
            } else {
                return ['success' => false, "msg" => $resBody["message"] ?? ""];
            }

        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $post_data = [
            'merchant_id'    => $data['merchant'] ?? $this->merchant,
            'amount'    => $data['request']->amount,
            'out_trade_no'   => $data['request']->order_number,
            'callback_url'   => $data['callback_url'],
            'payee_name' => $data['request']->bank_card_holder_name,
            'pay_address'  => $data['request']->bank_card_number,
            'network'    => $data['request']->bank_name,
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $return_data = json_decode($this->curl($data['url'], http_build_query($post_data)), true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if (isset($return_data['data']) && in_array($return_data['data']['status'], [0, 1])) {
            return ['success' => true];
        } else {
            return ['success' => false, 'msg' => $return_data['message'] ?? ''];
        }
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $post_data = [
            'out_trade_no'   => $data['request']->order_number,
            'merchant_id'   => $data['merchant'] ?? $this->merchant,
        ];
        $post_data['sign'] = $this->makesign($post_data);

        Log::debug(self::class, compact('post_data'));

        $return_data = json_decode($this->curl($data['queryDaifuUrl'], http_build_query($post_data)), true);

        Log::debug(self::class, compact('return_data'));

        if (isset($return_data['data']) && $return_data['data']['status'] === 0) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }

        if (isset($return_data['data']) && $return_data['data']['status'] === 1) {
            return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
        }

        if (isset($return_data['data']) && $return_data['data']['status'] === 2) {
            return ['success' => true, 'status' => Transaction::STATUS_FAILED, 'msg' => $return_data['message']];
        }

        return ['success' => false, 'msg' => $return_data['message'], 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all()['data'];

        if ($data['out_trade_no'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if ($data['status'] === 2) {
            return ['fail' => '失败'];
        }

        if ($data['status'] === 1) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $post_data = [
            'merchant_id' => $data['merchant']
        ];

        $post_data['sign'] = $this->makesign($post_data);

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

        //Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['data'])) {
            $balance = $result['data']['available_balance'];

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data)
    {
        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            $signstr .= "$k=$v&";
        }
        $signstr .= "secret_key=$this->key";
        return md5($signstr);
    }
}
