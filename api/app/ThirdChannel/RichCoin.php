<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;

class RichCoin extends ThirdChannel
{
    //Log名称
    public $log_name   = 'RichCoin';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api.richcoinpay.com/pay/create';
    public $xiafaUrl   = 'https://api.richcoinpay.com/remittance/create';
    public $daifuUrl   = 'https://api.richcoinpay.com/remittance/create';
    public $queryDepositUrl = 'https://api.richcoinpay.com/pay/query';
    public $queryDaifuUrl  = 'https://api.richcoinpay.com/remittance/query';
    public $queryBalanceUrl = 'https://api.richcoinpay.com/pay/balance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'OK';

    //白名单
    public $whiteIP = [];

    public $channelCodeMap = [
        'BANK_CARD' => 'bank'
      ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $post = [
            'pay_merchant_id' => $data['merchant'] ?? $this->merchant,
            'pay_amount' => $data['request']->amount,
            'pay_notify_url' => $data['callback_url'],
            'pay_order_id' => $data['request']->order_number,
            'pay_datetime' => now()->format('Y-m-d H:i:s'),
            'pay_method' => $this->channelCodeMap[$this->channelCode],
            'pay_subject' => '网购商品'
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['pay_real_name'] = $data['request']->real_name;
        }

        $post['pay_sign'] = $this->makesign($post);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['json' => $post]);
            $return_data = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('post', 'return_data'));

        if(isset($return_data['message']) && in_array($return_data['message'],['success'])){
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $return_data['data']['pay_amount'],
                'receiver_name' => $return_data['data']['pay_bank_owner'],
                'receiver_bank_name' => $return_data['data']['pay_bank_name'],
                'receiver_account' => $return_data['data']['pay_bank_no'],
                'receiver_bank_branch' => $return_data['data']['pay_bank_branch'],
                'pay_url' => $return_data['data']['pay_url'],
                'note' => $return_data['data']['pay_remark'],
                'created_at' => date('Y-m-d H:i:s'),
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
        $post = [
            'pay_merchant_id' => $data['merchant'] ?? $this->merchant,
            'pay_amount' => $data['request']->amount,
            'pay_order_id' => $data['request']->order_number,
            'pay_notify_url' => $data['callback_url'],
            'pay_bank_owner' => $data['request']->bank_card_holder_name,
            'pay_bank_acc' => $data['request']->bank_card_number,
            'pay_bank_name' => $data['request']->bank_name,
            'pay_bank_branch' => $data['request']->bank_name,
            'pay_datetime' => now()->format('Y-m-d H:i:s'),
        ];

        $post['pay_sign'] = $this->makesign($post);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['json' => $post]);
            $return_data = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('post', 'return_data'));

        if(isset($return_data['message']) && in_array($return_data['message'],['success'])){
            return ['success' => true];
        }else{
            return ['success' => false];
        }
        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        Log::debug(json_encode($data));
        $post = [
            'pay_order_id'   => $data['request']->order_number,
            'pay_merchant_id'   => $data['merchant'] ?? $this->merchant,
            'pay_datetime' => now()->format('Y-m-d H:i:s'),
        ];
        $post['pay_sign'] = $this->makesign($post);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], ['json' => $post]);
            $return_data = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug($data['request']->order_number . '_QUERY ::' .json_encode($return_data));

        if(isset($return_data['data']['pay_status']) && in_array($return_data['data']['pay_status'],[30000])){
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }else{
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['pay_order_id'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data['pay_amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (in_array($data['pay_status'], [30002, 30005])) {
            $map = [
                30002 => '驳回',
                30005 => '失败'
            ];
            return ['fail' => $map[$data['pay_status']]];
        }

        if (in_array($data['pay_status'], [30001])) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $post_data = [
            'pay_merchant_id'   => $data['merchant'],
            'pay_datetime' => now()->format('Y-m-d H:i:s')
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

        Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['message']) && in_array($result['message'],['success'])) {
            $balance = $result['pay_balance'];

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
            $signstr .= "$k=$v&";
        }
        $signstr .= "key=$this->key";
        return strtoupper(md5($signstr));
    }
}
