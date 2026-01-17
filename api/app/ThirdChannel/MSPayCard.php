<?php

namespace App\ThirdChannel;

use App\Model\Transaction;
use Illuminate\Http\Request;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;

class MSPayCard extends ThirdChannel
{
    //Log名称
    public $log_name   = 'MSPayCard';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://payment.mspays.xyz/{merchant}/orders/v4_1/scan';
    public $xiafaUrl   = 'https://payment.mspays.xyz/{merchant}/orders/pay';
    public $daifuUrl   = 'https://payment.mspays.xyz/{merchant}/orders/pay';
    public $queryDepositUrl = 'https://payment.mspays.xyz/{merchant}/orders/query';
    public $queryDaifuUrl  = 'https://payment.mspays.xyz/{merchant}/orders/query';
    public $queryBalanceUrl = 'https://payment.mspays.xyz/{merchant}/orders/deposit/balance';
    public $cardDepositUrl = "https://payment.mspays.xyz/{merchant}/orders/v4/scan";

    //预设商户号
    public $merchant    = 'MQ1U2UISXF';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'SUCCESS';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'REALNAMEBANK',
    ];

    public $bankMap = [
        '上海银行' => 'BOS',
        '北京银行' => 'BOB',
        '华夏银行' => 'HXB',
        '广发银行' => 'CGB',
        '光大银行' => 'CEB',
        '民生银行' => 'CMBC',
        '中信银行' => 'ECITIC',
        '兴业银行' => 'CIB',
        '中国邮政储蓄银行' => 'PSBC',
        '交通银行' => 'BOCOM',
        '建设银行' => 'CCBC',
        '中国银行' => 'BOC',
        '招商银行' => 'CMB',
        '农业银行' => 'ABC',
        '平安银行' => 'PAB',
        '宁波银行' => 'NBCB',
        '工商银行' => 'ICBC',
        '浦发银行' => 'SPDB',
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $headers = [
            'Content-Type: application/json',
        ];

        $post = [
            'merchantCode'       => $data['merchant'] ?? $this->merchant,
            'paymentTypeCode'    => $this->channelCodeMap[$this->channelCode],
            'amount'             => $data['request']->amount,
            'successUrl'         => $data['callback_url'],
            'merchantOrderId'    => $data['system_order_number'],
            'merchantMemberId'   => $data['merchant'] ?? $this->merchant,
            'merchantMemberIp'   => $data['request']->client_ip ?? $data['client_ip']
        ];

        $post['sign'] = $this->makesign($post);

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['payerName'] = $data['request']->real_name;
        }

        Log::debug(self::class, compact('post'));

        $url = str_replace('{merchant}', $data['key2'], $data['url']);
        $return_data = json_decode($this->curl($url, json_encode($post), $headers), true);

        Log::debug(self::class, ['order' => $data['request']->order_number, 'return_data' => $return_data]);

        if (isset($return_data['result']) && $return_data['result'] == true) {
            $info = $return_data["data"];
            $ret = [
                'pay_url' => "",
                'receiver_name' => $info["bankAccountName"],
                'receiver_bank_name' => $info["bankName"],
                'receiver_account' => $info['bankAccountNumber'],
                'receiver_bank_branch' => $info['bankAccountBranch'],
            ];
        } else {
            return ['success' => false, "msg" => $return_data["errorMsg"]["descript"]];
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
        if (!isset($this->bankMap[$data['request']->bank_name])) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $this->key = $data['key'];

        $headers = [
            'Content-Type: application/json',
        ];

        $post_data = [
            'merchantCode'             => $data['merchant'] ?? $this->merchant,
            'amount'                   => $data['request']->amount,
            'merchantOrderId'          => $data['system_order_number'],
            'successUrl'               => $data['callback_url'],
            'bankAccountName'          => $data['request']->bank_card_holder_name,
            'bankAccountNumber'        => $data['request']->bank_card_number,
            'bankCode'                 => $this->bankMap[$data['request']->bank_name],
            'branch'                   => $data['request']->bank_name,
            'province'                 => $data['request']->bank_name,
            'city'                     => $data['request']->bank_name
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $url = str_replace('{merchant}', $data['key2'], $data['url']);
        $return_data = json_decode($this->curl($url, json_encode($post_data), $headers), true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if (isset($return_data['result']) && $return_data['result'] == true) {
            return ['success' => true];
        } else {
            return ['success' => false];
        }
        return ['success' => false];
    }

    public function queryDaifu($data)
    {

        $headers = [
            'Content-Type: application/json',
        ];

        $post_data = [
            'merchantOrderId'      => $data['system_order_number'],
            'merchantCode'         => $data['merchant'] ?? $this->merchant,
        ];
        $post_data['sign'] = $this->makesign($post_data);
        $url = str_replace('{merchant}', $data['key2'], $data['queryDaifuUrl']);
        $return_data = json_decode($this->curl($url, json_encode($post_data), $headers), true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if (isset($return_data['result']) && $return_data['result'] == true) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } else {
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if (($data['merchantOrderId'] != $transaction->order_number) && ($data['merchantOrderId'] != $transaction->system_order_number)) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'], ['Success'])) {
            return ['success' => true];
        }

        if (isset($data['status']) && in_array($data['status'], ['Failed', 'Unpaid'])) {
            $map = [
                'Failed' => '失败',
                'Unpaid' => '支付超時',
            ];
            return ['fail' => $map[$data['status']]];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $headers = [
            'Content-Type: application/json',
        ];
        $post_data = [
            'merchantCode'         => $data['merchant'] ?? $this->merchant,
            "currencyCode" => "CNY"
        ];
        $post_data['sign'] = $this->makesign([
            'merchantCode' => $data['merchant'] ?? $this->merchant,
        ]);
        $url = str_replace('{merchant}', $data['key2'], $data['queryBalanceUrl']);
        $return_data = json_decode($this->curl($url, json_encode($post_data), $headers), true);

        // Log::debug(self::class, compact('post_data', 'return_data'));

        if (isset($return_data['result']) && $return_data['result'] == true) {
            $balance = $return_data["data"]["balance"];
            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);
            return $balance;
        }
        return 0;
    }

    public function makesign($data)
    {
        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            if ($v != null && $v != "") {
                if ($k === array_key_last($data)) {
                    $signstr = $signstr . $k . "=" . $v;
                } else {
                    $signstr = $signstr . $k . "=" . $v . "|";
                }
            }
        }
        return md5($signstr . $this->key);
    }
}
