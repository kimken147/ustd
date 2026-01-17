<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Utils\BCMathUtil;
use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class SumPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'SumPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://entrance.sumpay.cn/gateway.htm';
    public $xiafaUrl   = 'https://entrance.sumpay.cn/gateway.htm';
    public $daifuUrl   = 'https://entrance.sumpay.cn/gateway.htm';
    public $queryDepositUrl = 'https://entrance.sumpay.cn/gateway.htm';
    public $queryDaifuUrl  = 'https://entrance.sumpay.cn/gateway.htm';
    public $queryBalanceUrl = 'https://entrance.sumpay.cn/gateway.htm';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //AES加密向量密鑰
    public $vector      = '';

    //回传字串
    public $success = 'ok';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'QR_WECHATPAY' => '09',
        'QR_ALIPAY' => '10',
        'H5_ALIPAY' => '10'
    ];

    public $bankMap = [

    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $transaction = Transaction::where('order_number', $data['request']->order_number)->first();
        $user = $transaction->to->username;

        $post = [
            'service'       => 'fosun.sumpay.api.pay.qrcode.trade.apply',
            'version'       => '1.0',
            'app_id'        => $data['merchant'] ?? $this->merchant,
            'timestamp'     => now()->format('YmdHis'),
            'terminal_type' => 'wap',
            'sign_type'     => 'CERT',
            'format'        => 'JSON',
            'business_code' => $this->channelCodeMap[$this->channelCode],

            'mer_no'        => $data['merchant'] ?? $this->merchant,
            'trade_code'    => 'T0002',
            'user_id'       => Str::random(10),
            'user_id_type'  => 1,
            'order_no'      => $data['request']->order_number,
            'order_time'    => now()->format('YmdHis'),
            'order_amount'  => $data['request']->amount,
            'need_notify'   => 1,
            'notify_url'    => base64_encode($data['callback_url']),
            'need_return'   => 0,
            'currency'      => 'CNY',
            'business_code' => $this->channelCodeMap[$this->channelCode],
            'goods_name'    => base64_encode('商品信息'),
            'goods_num'     => 1,
            'goods_type'    => 2,
            'user_ip_addr'  => $data['request']->client_ip ?? $data['client_ip'],

            // 'amount'        => 0
        ];

        $post['sign'] = $this->makesign($post);
        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'form_params' => $post
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['resp_code']) && in_array($row['resp_code'], ['000000'])) {
            $dataDecode = json_decode($row['sumpay_qrcode_pay_response'],true);
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'      => $dataDecode['code_url'],
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

    }

    public function queryDaifu($data)
    {
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if (isset($data['order_no']) && $data['order_no'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['success_amount']) && $data['success_amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'],[1])) {
            return ['success' => true];
        }

        if (isset($data['status']) && in_array($data['status'],[0])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        return 99999999;
    }

    public function makesign($data){
        unset($data['sign_type']);
        ksort($data);
        $prkey = "-----BEGIN PRIVATE KEY-----\n".wordwrap($this->key, 64, "\n",true)."\n-----END PRIVATE KEY-----\n";

        $signstr = [];
        foreach ($data as $k => $v) {
            if ($v !== null && $v !== "") {
                $signstr[] = $k . "=" . $v;
            }
        }
        $signstr = implode('&', $signstr);

        return openssl_sign($signstr, $sign, $prkey, 'sha256') ? base64_encode($sign) : false;
    }
}
