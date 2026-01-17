<?php

namespace App\ThirdChannel;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ZTPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'ZTPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://www.onetais.com/Pay_Index.html';
    public $xiafaUrl   = 'https://www.onetais.com/Payment_Dfpay_add.html';
    public $daifuUrl   = 'https://www.onetais.com/Payment_Dfpay_add.html';
    public $queryDepositUrl = 'https://www.onetais.com/Pay_Trade_query.html';
    public $queryDaifuUrl  = 'https://www.onetais.com/Payment_Dfpay_query.html';
    public $queryBalanceUrl = 'https://www.onetais.com/Payment_Dfpay_balance.html';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'OK';

    //白名单
    public $whiteIP = ['169.129.221.204'];

    public $channelCodeMap = [
        'BANK_CARD' => 1
    ];
    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
        ];

        $post = [
            'pay_memberid'    => intval($data['merchant']) ?? intval($this->merchant),
            'pay_applydate'   => date("Y-m-d H:i:s"),
            'pay_bankcode'    => intval($this->channelCodeMap['BANK_CARD']),
            'pay_amount'      => $data['request']->amount,
            'pay_notifyurl'   => $data['callback_url'],
            'pay_callbackurl' => $data['callback_url'],
            'pay_orderid'     => $data['request']->order_number,
        ];

        $post['pay_md5sign'] = $this->makesign($post);

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['fkrname'] = $data['request']->real_name;
        }
        $post['pay_productname'] = '团购商品';
        $post['format'] = 'JSON';

        Log::debug(self::class, compact('post'));

        $return_data = json_decode($this->curl($data['url'],http_build_query($post),$headers),true);

        Log::debug(self::class, compact('return_data'));

        if(isset($return_data['status']) && in_array($return_data['status'], ['success'])){
            $ret = [
                'order_number'       => $return_data['mch_order_no'],
                'amount'             => $return_data['amount'],
                'receiver_name'      => $return_data['name'],
                'receiver_bank_name' => $return_data['bank_name'],
                'receiver_account'   => $return_data['bank_card_number'],
                'pay_url'            => $return_data['url'],
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

        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $post_data = [
            'mchid'              => intval($data['merchant']) ?? intval($this->merchant),
            'money'              => $data['request']->amount,
            'out_trade_no'       => $data['request']->order_number,
            'notifyurl'          => $data['callback_url'],
            'accountname'        => $data['request']->bank_card_holder_name,
            'cardnumber'         => $data['request']->bank_card_number,
            'bankname'           => $data['request']->bank_name,
            'subbranch'          => $data['request']->bank_name,
            'province'           => $data['request']->bank_name,
            'city'               => $data['request']->bank_name,
        ];

        $post_data['pay_md5sign'] = $this->makesign($post_data);
        $return_data = json_decode($this->curl($data['url'],http_build_query($post_data),$headers),true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if(isset($return_data['status']) && in_array($return_data['status'],['success'])){
            return ['success' => true];
        }else{
            return ['success' => false];
        }
    }

    public function queryDaifu($data)
    {
        $headers = [
            'Content-Type: application/x-www-form-urlencoded'
        ];

        $post_data = [
            'out_trade_no'      => $data['request']->order_number,
            'mchid'             => intval($data['merchant']) ?? intval($this->merchant)
        ];
        $post_data['pay_md5sign'] = $this->makesign($post_data);
        $return_data = json_decode($this->curl($data['queryDaifuUrl'],http_build_query($post_data),$headers),true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if(isset($return_data['status']) && in_array($return_data['status'],['success']) && in_array($return_data['refCode'],[1])){
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }else{
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if (isset($data['orderid']) && $data['orderid'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['realamount']) && $data['realamount'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        if (isset($data['out_trade_no'])) {

            if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
                return ['error' => '代付金额不正确'];
            }

            if (isset($data['refCode']) && in_array($data['refCode'],[1])) {
                return ['success' => true];
            }

            if (isset($data['refCode']) && in_array($data['refCode'],[2])) {
                return ['fail' => '失败'];
            }
        }

        if (isset($data['returncode']) && in_array($data['returncode'],['00'])) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        return 99999999;
    }

    public function makesign($data){
        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            $signstr = $signstr . $k . "=" . $v . "&";
        }
        return strtoupper(md5($signstr . "key=" . $this->key));
    }
}
