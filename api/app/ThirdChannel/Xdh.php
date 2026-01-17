<?php

namespace App\ThirdChannel;

use App\Model\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Xdh extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Xdh';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://gtb.pastfnz.com/order/create';
    public $xiafaUrl   = 'https://gtb.pastfnz.com/payout/create';
    public $daifuUrl   = 'https://gtb.pastfnz.com/payout/create';
    public $queryDepositUrl = '';
    public $queryDaifuUrl  = 'https://gtb.pastfnz.com/payout/status';
    public $queryBalanceUrl = '';

    //预设商户号
    public $merchant    = '8909';

    //预设密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['13.94.46.176'];

    public $channelCodeMap = [
        'QR_ALIPAY' => 1,
        'ALIPAY_BANK' => 2,
        'BANK_CARD' => 4
    ];

    public $bankMap = [
        '浦发银行' => '上海浦东发展银行',
        '上海银行' => '上海银行',
        '上饶银行' => '上饶银行',
        '东莞银行' => '东莞银行',
        '中信银行' => '中信银行',
        '光大银行' => '中国光大银行',
        '农业银行' => '中国农业银行',
        '工商银行' => '中国工商银行',
        '建设银行' => '中国建设银行',
        '民生银行' => '中国民生银行',
        '中国邮储银行' => '中国邮政储蓄银行',
        '中国邮政银行' => '中国邮政储蓄银行',
        '邮政银行' => '中国邮政储蓄银行',
        '邮储银行' => '中国邮政储蓄银行',
        '中国邮政储蓄银行' => '中国邮政储蓄银行',
        '中国银行' => '中国银行',
        '平安银行' => '平安银行',
        '临商银行' => '临商银行',
        '交通银行' => '交通银行',
        '兴业银行' => '兴业银行',
        '内蒙古银行' => '内蒙古银行',
        '北京银行' => '北京银行',
        '华夏银行' => '华夏银行',
        '华融湘江银行' => '华融湘江银行',
        '南京银行' => '南京银行',
        '南充市商业银行' => '南充市商业银行',
        '台州银行' => '台州银行',
        '吉林银行' => '吉林银行',
        '国家开发银行' => '国家开发银行',
        '大连银行' => '大连银行',
        '天津农商银行' => '天津农商银行',
        '天津银行' => '天津银行',
        '威海市商业银行' => '威海市商业银行',
        '宁夏银行' => '宁夏银行',
        '宁波银行' => '宁波银行',
        '宜宾市商业银行' => '宜宾市商业银行',
        '富滇银行' => '富滇银行',
        '平顶山银行' => '平顶山银行',
        '广州银行' => '广州银行',
        '广发银行' => '广发银行',
        '廊坊银行' => '廊坊银行',
        '德州银行' => '德州银行',
        '徽商银行' => '徽商银行',
        '恒丰银行' => '恒丰银行',
        '成都农商银行' => '成都农商银行',
        '成都银行' => '成都银行',
        '承德银行' => '承德银行',
        '招商银行' => '招商银行',
        '昆仑银行' => '昆仑银行',
        '杭州银行' => '杭州银行',
        '汉口银行' => '汉口银行',
        '洛阳银行' => '洛阳银行',
        '济宁银行' => '济宁银行',
        '浙商银行' => '浙商银行',
        '浙江民泰商业银行' => '浙江民泰商业银行',
        '浙江泰隆商业银行' => '浙江泰隆商业银行',
        '浙江稠州商业银行' => '浙江稠州商业银行',
        '渤海银行' => '渤海银行',
        '温州银行' => '温州银行',
        '湖北银行' => '湖北银行',
        '潍坊银行' => '潍坊银行',
        '盛京银行' => '盛京银行',
        '石嘴山银行' => '石嘴山银行',
        '福建海峡银行' => '福建海峡银行',
        '绍兴银行' => '绍兴银行',
        '苏州银行' => '苏州银行',
        '莱商银行' => '莱商银行',
        '营口银行' => '营口银行',
        '衡水银行' => '衡水银行',
        '西安银行' => '西安银行',
        '赣州银行' => '赣州银行',
        '邢台银行' => '邢台银行',
        '郑州银行' => '郑州银行',
        '鄂尔多斯银行' => '鄂尔多斯银行',
        '重庆三峡银行' => '重庆三峡银行',
        '锦州银行' => '锦州银行',
        '长沙银行' => '长沙银行',
        '陕西信合' => '陕西信合',
        '青岛银行' => '青岛银行',
        '青海银行' => '青海银行',
        '韩亚银行' => '韩亚银行',
        '齐商银行' => '齐商银行',
        '齐鲁银行' => '齐鲁银行',
        '龙江银行' => '龙江银行',
        '福建省农村信用社' => '福建省农村信用社'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $this->key2 = $data['key2'];
        $post = [
            'merchantNo' => $data['merchant'] ?? $this->merchant,
            'channelNo' => $this->channelCodeMap[$this->channelCode],
            'amount' => $data['request']->amount,
            'notifyUrl' => $data['callback_url'],
            'orderNo' => $data['request']->order_number,
            'datetime'  => now()->format('Y-m-d H:i:s'),
            'time' => now()->timestamp,
            'appSecret' => $this->key,
            'userNo' => '',
            'discount' => '',
            'extra' => ''
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['payeeName'] = $data['request']->real_name;
        }

        $post['sign'] = strtoupper(md5(hash('sha256', $this->makesign($post))));

        $return_data = json_decode($this->curl($data['url'],http_build_query($post)),true);

        Log::debug(self::class, compact('post', 'return_data'));

        if(isset($return_data['code']) && in_array($return_data['code'], [0])){
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $return_data['amount'],
                'receiver_name' => $return_data['payeeTitle'] ?? null,
                'receiver_bank_name' => $return_data['payeeBankName'] ?? null,
                'receiver_account' => $return_data['payeeAccountNumber'] ?? null,
                'receiver_bank_branch' => $return_data['payeeAccountBankBranch'] ?? null,
                'pay_url' => $return_data['targetUrl'],
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
        if (isset($this->bankMap[$data['request']->bank_name])) {
            $bankName = $this->bankMap[$data['request']->bank_name];
        } else if (in_array($data['request']->bank_name, $this->bankMap)) {
            $bankName = $data['request']->bank_name;
        } else {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $this->key = $data['key'];
        $this->key2 = $data['key2'];
        $post_data = [
            'merchantNo' => $data['merchant'] ?? $this->merchant,
            'amount'  => $data['request']->amount,
            'orderNo' => $data['request']->order_number,
            'notifyUrl' => $data['callback_url'],
            'reverseUrl' => $data['callback_url'],
            'name' => $data['request']->bank_card_holder_name,
            'bankAccount' => $data['request']->bank_card_number,
            'bankName' =>  $bankName,
            'appSecret' => $this->key,
            'datetime'  => now()->format('Y-m-d H:i:s'),
            'time' => now()->timestamp,
            'extra' => '',
            'mobile' => ''
        ];

        $post_data['sign'] = strtoupper(md5(hash('sha256', $this->makesign($post_data))));
        $return_data = json_decode($this->curl($data['url'],http_build_query($post_data)),true);


        Log::debug(self::class, compact('post_data', 'return_data'));

        if(isset($return_data['code']) && in_array($return_data['code'],[0])){
            return ['success' => true];
        }else{
            return ['success' => false];
        }
        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $this->key2 = $data['key2'];

        $post_data = [
            'orderNo'   => $data['request']->order_number,
            'merchantNo'   => $data['merchant'] ?? $this->merchant,
            'time' => now()->timestamp,
            'appSecret' => $this->key,
            'tradeNo' => ''
        ];
        $post_data['sign'] = strtoupper(md5(hash('sha256', $this->makesign($post_data))));
        $return_data = json_decode($this->curl($data['queryDaifuUrl'],http_build_query($post_data)),true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if(isset($return_data['status']) && in_array($return_data['status'],['PENDING', 'PAID'])){
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }else{
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['orderNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (in_array($data['status'], ['CANCELLED'])) {
            $map = [
                'CANCELLED' => '失败'
            ];
            return ['fail' => $map[$data['status']]];
        }

        if (in_array($data['status'], ['PAID', 'MANUAL PAID'])) {
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
        $data = collect($data)->except(['userName', 'channelNo', 'payeeName', 'appSecret', 'bankBranch', 'memo'])->toArray();

        $signstr = '';
        foreach ($data as $k => $v) {
            $signstr .= "$k=$v&";
        }

        $signstr = substr($signstr, 0, -1) . $this->key2;
        return $signstr;
    }
}
