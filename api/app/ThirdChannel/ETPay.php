<?php

namespace App\ThirdChannel;

use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ETPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'ETPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://etpay888.com/api/pay_order';
    public $xiafaUrl   = 'https://etpay888.com/api/payments/pay_order';
    public $daifuUrl   = 'https://etpay888.com/api/payments/pay_order';
    public $queryDepositUrl = 'https://etpay888.com/api/query_transaction';
    public $queryDaifuUrl  = 'https://etpay888.com/api/payments/query_transaction';
    public $queryBalanceUrl = 'https://etpay888.com/api/payments/balance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'OK';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => '1659',
        'RE_ALIPAY' => '918',
        "QR_ALIPAY" => "4137",
        'GCASH'     => '1302'
    ];

    public $bankMap = [
        'GCash' => 'GCASH'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $post = [
            'pay_customer_id' => intval($data['merchant']) ?? intval($this->merchant),
            'pay_apply_date'  => strtotime("now"),
            'pay_channel_id'  => intval($this->channelCodeMap[$this->channelCode]),
            'pay_amount'      => $data['request']->amount,
            'pay_notify_url'  => $data['callback_url'],
            'pay_order_id'    => $data['request']->order_number,
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['user_name'] = $data['request']->real_name;
        }

        $post['pay_md5_sign'] = $this->makesign($post);
        Log::debug(self::class, compact('post'));

        $result = $this->curl($data['url'], http_build_query($post));
        $return_data = json_decode($result, true);

        Log::debug(self::class, ['order' => $data['request']->order_number, 'return_data' => $return_data]);

        if (isset($return_data['code']) && in_array($return_data['code'], [0])) {
            if ($this->channelCode === 'BANK_CARD') {
                $ret = [
                    'order_number' => $return_data['data']['order_id'],
                    'amount' => $return_data['data']['real_price'],
                    'receiver_name' => $return_data['data']['bank_owner'],
                    'receiver_bank_name' => $return_data['data']['bank_name'],
                    'receiver_account' => $return_data['data']['bank_no'],
                    'receiver_bank_branch' => $return_data['data']['bank_from'],
                    'pay_url' => $return_data['data']['view_url'],
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            } else {
                $ret = [
                    'order_number' => $return_data['data']['order_id'],
                    'pay_url' => $return_data['data']['view_url'],
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            }
        } else {
            return ['success' => false];
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

        $post_data = [
            'pay_customer_id'    => intval($data['merchant']) ?? intval($this->merchant),
            'pay_apply_date'     => strtotime("now"),
            'pay_amount'         => $data['request']->amount,
            'pay_order_id'       => $data['request']->order_number,
            'pay_notify_url'     => $data['callback_url'],
            'pay_account_name'   => $data['request']->bank_card_holder_name,
            'pay_card_no'        => $data['request']->bank_card_number,
            'pay_bank_name'      => $this->bankMap[$data['request']->bank_name] ?? $data['request']->bank_name,
        ];

        $post_data['pay_md5_sign'] = $this->makesign($post_data);
        $result = $this->curl($data['url'], http_build_query($post_data));
        $return_data = json_decode($result, true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if (isset($return_data['code']) && in_array($return_data['code'], [0])) {
            return ['success' => true];
        } else {
            return ['success' => false];
        }
        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'pay_order_id'      => $data['request']->order_number,
            'pay_customer_id'   => intval($data['merchant']) ?? intval($this->merchant),
            'pay_apply_date'    => strtotime("now"),
        ];
        $post_data['pay_md5_sign'] = $this->makesign($post_data);
        $return_data = json_decode($this->curl($data['queryDaifuUrl'], http_build_query($post_data)), true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if (isset($return_data['code']) && in_array($return_data['code'], [0])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } else {
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['order_id'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['real_amount']) && $data['real_amount'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '代付金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'], ['30000'])) {
            return ['success' => true];
        }

        if (isset($data['transaction_code']) && in_array($data['transaction_code'], ['30000'])) {
            return ['success' => true];
        }

        if (isset($data['transaction_code']) && in_array($data['transaction_code'], ['40000'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'pay_customer_id'   => intval($data['merchant']) ?? intval($this->merchant),
            'pay_apply_date'    => strtotime("now"),
        ];
        $post_data['pay_md5_sign'] = $this->makesign($post_data);
        $return_data = json_decode($this->curl($data['queryBalanceUrl'], http_build_query($post_data)), true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if (isset($return_data['code']) && in_array($return_data['code'], [0])) {
            $balance = $return_data['data']['balance'];

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
            if ($v != null && $v != "") {
                $signstr = $signstr . $k . "=" . $v . "&";
            }
        }
        return strtoupper(md5($signstr . "key=" . $this->key));
    }
}
