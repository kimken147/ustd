<?php

namespace App\ThirdChannel;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

use App\Models\ThirdChannel as ThirdChannelModel;
class YFHL extends ThirdChannel
{
    //Log名称
    public $log_name   = 'YFHL';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://zuanshizhifu.net/api/Gateway/create';
    public $xiafaUrl   = 'https://zuanshizhifu.net/api/Gateway/withdraw';
    public $daifuUrl   = 'https://zuanshizhifu.net/api/Gateway/withdraw';
    public $queryDepositUrl = 'https://zuanshizhifu.net/Gateway/order-query';
    public $queryDaifuUrl  = 'https://zuanshizhifu.net/api/Gateway/checkwithdraw';
    public $queryBalanceUrl = 'https://zuanshizhifu.net/api/Gateway/getbalance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'bank_bank'
    ];

    public $bankMap = [
        '工商银行' => 'ICBC',
        '农业银行' => 'ABC',
        '中国银行' => 'BOC',
        '建设银行' => 'CCB',
        '交通银行' => 'COMM',
        '中信银行' => 'CITIC',
        '光大银行' => 'CEB',
        '华夏银行' => 'HXB',
        '民生银行' => 'CMBC',
        '广东发展银行' => 'GDB',
        '平安银行' => 'SZPAB',
        '招商银行' => 'CMB',
        '兴业银行' => 'CIB',
        '浦东发展银行' => 'SPDB',
        '北京银行' => 'BCCB',
        '城市商业银行' => 'CITYBANK',
        '广州市商业银行' => 'GZCB',
        '汉口银行' => 'HKBCHINA',
        '杭州银行' => 'HCCB',
        '晋城市商业银行' => 'SXJS',
        '南京银行' => 'NJCB',
        '宁波银行' => 'NBCB',
        '上海银行' => 'BOS',
        '温州市商业银行' => 'WZCB',
        '长沙银行' => 'CSCB',
        '浙江稠州商业银行' => 'CZCB',
        '广州市农信社' => 'GNXS',
        '农村商业银行' => 'RCB',
        '顺德农信社' => 'SDE',
        '恒丰银行' => 'EGBANK',
        '浙商银行' => 'CZB',
        '农村合作银行' => 'URCB',
        '渤海银行' => 'CBHB',
        '徽商银行' => 'HSBANK',
        '村镇银行' => 'COUNTYBANK',
        '重庆三峡银行' => 'CCQTGB',
        '上海农村商业银行' => 'SHRCB',
        '城市信用合作社' => 'UCC',
        '北京农商行' => 'BJRCB',
        '湖南农信社' => 'HNNXS',
        '农村信用合作社' => 'RCC',
        '深圳农村商业银行' => 'SNXS',
        '尧都信用合作联社' => 'YDXH',
        '珠海市农村信用合作社' => 'ZHNX',
        '中国邮储银行' => 'PSBC',
        '东亚银行' => 'HKBEA',
        '集友银行' => 'CYB',
        '渣打银行' => 'SCB',
        '深圳发展银行' => 'SDB'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $post = [
            'account_id' => $data['merchant'] ?? intval($this->merchant),
            'thoroughfare' => $this->channelCodeMap[$this->channelCode],
            'amount'      => sprintf("%.2f", $data['request']->amount),
            'callback_url'  => $data['callback_url'],
            'success_url' => 'https://',
            'error_url' => 'https://',
            'out_trade_no'    => $data['request']->order_number,
            'nonce_str' => Str::random(32),
            'robin' => 1,
            'content_type' => 'json_new'
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['ext'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makeDepositSign($post);
        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['json' => $post]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if(isset($result['code']) && in_array($result['code'], [200])){
            $ret = [
                'pay_url' => $result['data']['jump_url'],
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
        if (!isset($this->bankMap[$data['request']->bank_name])) {
          return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $this->key = $data['key'];

        $postData = [
            'acc_id' => $data['merchant'] ?? $this->merchant,
            'amount' => sprintf("%.2f", $data['request']->amount),
            'orderSn' => $data['request']->order_number,
            'callback_url' => $data['callback_url'],
            'card_name' => $data['request']->bank_card_holder_name,
            'card_no' => $data['request']->bank_card_number,
            'bank_code' => $this->bankMap[$data['request']->bank_name],
            'branch' => $data['request']->bank_name
        ];

        $postData['sign'] = $this->makeDaifuSign($postData);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['json' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if(isset($result['code']) && in_array($result['code'], [200])){
            return ['success' => true];
        }else{
            return ['success' => false];
        }
        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $postData = [
            'acc_id' => $data['merchant'] ?? $this->merchant,
            'orderSn' => $data['request']->order_number
        ];
        $postData['sign'] = $this->makeDaifuQuerySign($postData);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], ['json' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('result'));

        if(isset($result['code']) && in_array($result['code'],[0])){
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }else{
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['out_trade_no'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'], ['success'])) {
            return ['success' => true];
        }

        if (isset($data['status']) && in_array($data['status'], ['fail'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $post_data = [
            'acc_id' => $data['merchant']
        ];

        $post_data['sign'] = $this->makeDaifuQuerySign($post_data);

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

        if (isset($result['code']) && in_array($result['code'],[200])) {
            $balance = $result['data']['balance'];

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makeDepositSign($data) {
        $str_a = md5(sprintf("%.2f", $data['amount']) . $data['out_trade_no']);
        return md5(strtolower($this->key). $str_a);
    }

    public function makeDaifuSign($data) {
        $data = Arr::only($data, ['acc_id', 'amount']);
        $data['key'] = strtolower($this->key);

        return md5(http_build_query($data));
    }

    public function makeDaifuQuerySign($data) {
        $data['key'] = strtolower($this->key);
        return md5(http_build_query($data));
    }
}
