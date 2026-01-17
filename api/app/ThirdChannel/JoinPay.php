<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class JoinPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'JoinPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://trade.joinpay.com/tradeRt/uniPay';
    public $xiafaUrl   = '';
    public $daifuUrl   = '';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = '';
    public $queryBalanceUrl = '';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_UNION_H5 => "UNIONPAY_H5",
        Channel::CODE_ALIPAY_H5 => 'ALIPAY_H5',
        Channel::CODE_WECHATPAY_H5 => 'WEIXIN_H5_PLUS'
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            'p0_Version' => 2.5,
            "p1_MerchantNo" => $data["merchant"],
            'p2_OrderNo' => $data['request']->order_number,
            'p3_Amount' => floatval($data['request']->amount),
            'p4_Cur' => '1',
            'p5_ProductName' => '支付',
            'p9_NotifyUrl' => $data['callback_url'],
            'q1_FrpCode' => $this->channelCodeMap[$this->channelCode],
            'qa_TradeMerchantNo' => '777196300843056',
        ];

        $postBody["hmac"] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'form_params' => $postBody,
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            return ['success' => false, 'msg' => $message];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if ($row["ra_Code"] == 100) {
            $ret = [
                'pay_url'   => $row['rc_Result'],
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ["success" => false, 'msg' => $row["rb_CodeMsg"]];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if (isset($data['r2_OrderNo']) && $data['r2_OrderNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['r3_Amount']) && $data['r3_Amount'] != $transaction->amount) {
            return ['error' => '代付金额不正确'];
        }

        //代付检查状态
        if (isset($data['r6_Status']) && in_array($data["r6_Status"], ['100'])) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        return 0;
    }

    public function makesign($data, $key)
    {
        $strSign = implode('', array_filter($data, function ($value) {
            return $value !== '' && $value !== null;
        }));

        $strSign .= $key;

        return md5($strSign);
    }
}
