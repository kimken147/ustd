<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Utils\BCMathUtil;
use App\Models\ThirdChannel as ThirdChannelModel;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class LgvPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'LgvPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://bmyl.uas-gw.info/v4/deposit/';
    public $xiafaUrl   = 'https://bmyl.uas-gw.info/v4/withdrawal/';
    public $daifuUrl   = 'https://bmyl.uas-gw.info/v4/withdrawal/';
    public $queryDepositUrl = 'https://bmyl.uas-gw.info/v4/deposit/TEST1/order';
    public $queryDaifuUrl  = 'https://bmyl.uas-gw.info/v4/withdrawal/';
    public $queryBalanceUrl = 'https://bmyl.uas-gw.info/v4/withdrawal/';

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
        'BANK_CARD' => 'banks_c2c'
    ];

    public $bankMap = [
        '中国银行' => 'BKCH',
        '招商银行' => 'CMBC',
        '交通银行' => 'COMM',
        '建设银行' => 'PCBC',
        '工商银行' => 'ICBk',
        '农业银行' => 'ABOC',
        '邮政银行' => 'PSBC',
        '中国邮政储蓄银行' => 'PSBC',
        '光大银行' => 'EVER',
        '民生银行' => 'MSBC',
        '中信银行' => 'CIBK',
        '浦发银行' => 'SPDB',
        '广发银行' => 'GDBK',
        '华夏银行' => 'HXBK',
        '兴业银行' => 'FJIB',
        '平安银行' => 'SZDB',
        '北京银行' => 'BJCN',
        '南京银行' => 'NJCB',
        '杭州银行' => 'HZCB',
        '宁波银行' => 'BKNB',
        '上海银行' => 'BOSH',
        '渤海银行' => 'CHBH',
        '浙商银行' => 'ZJCB',
        '上海农商银行' => 'SHRC',
        '深圳农商银行' => 'SRCC',
        '顺德农商银行' => 'RCCS',
        '北京农商银行' => 'BRCB',
        '东亚银行' => 'BEAS',
        '天津银行' => 'TCCB',
        '长沙银行' => 'CHCC',
        '恒丰银行' => 'HFBA',
        '徽商银行' => 'HSBANK',
        '浙江稠州商业银行' => 'CZCB',
        '浙江泰隆商业银行' => 'ZJTL',
        '其它银行' => 'OB',
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $this->vector = $data['key2'];
        $url = $data['url'] . $data['merchant'] . '/forward';
        $post = [
            'order_no'   => $data['request']->order_number,
            'amount'     => $data['request']->amount,
            'gateway'    => $this->channelCodeMap[$this->channelCode],
            'ip'         => $data['request']->client_ip ?? $data['client_ip'],
            'notify_url' => $data['callback_url'],
            'rank'       => '1',
            'user_id'    => 'user'
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['real_name'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makesign($post);
        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'json' => $post
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['ok']) && $row['ok']) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'   => $row['data']['url'],
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
        $this->vector = $data['key2'];
        $url = $data['url'] . $data['merchant'] . '/forward';
        $post_data = [
            'order_no'            => $data['request']->order_number,
            'amount'              => $data['request']->amount,
            'bank'                => $this->bankMap[$data['request']->bank_name] ?? $this->bankMap['其它银行'],
            'card_holder'         => $data['request']->bank_card_holder_name,
            'card_no'             => $data['request']->bank_card_number,
            'notify_url'          => $data['callback_url'],
            'platform_created_at' => now()->format('Y-m-d H:m:s'),
        ];

        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'json' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post_data', 'row'));

        if (isset($row['ok']) && $row['ok']) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $url = $data['queryDaifuUrl'] . $data['merchant'] . '/order/' . $data['request']->order_number;

        // $post_data = [
        //     'mchTransOrderNo' => $data['request']->order_number,
        //     'mchId'           => intval($data['merchant']) ?? intval($this->merchant),
        //     'appId'           => $data['key2'],
        // ];
        // $post_data['sign'] = $this->makesign($post_data);
        // $params = json_encode($post_data);
        // $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('GET', $url);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'post_data', 'response'));

        if (isset($row['ok']) && $row['ok']) {
            if (in_array($row['status'], ['created', 'issued', 'in-progress'])) {
                return ['success' => true, 'status' => Transaction::STATUS_PAYING];
            }
            if (in_array($row['status'], ['succeed'])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($row['status'], ['failed', 'expired', 'unconfirmable'])) {
                return ['success' => true, 'status' => Transaction::STATUS_FAILED];
            }

        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $this->decrypt($request->content, $transaction);

        Log::debug(self::class . ' decrypt', ['result' => $data]);

        $data = json_decode($data, true);

        if (isset($data['order_no']) && $data['order_no'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['actual_amount']) && $data['actual_amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['order_no']) && isset($data['status']) && in_array($data['status'],['succeed'])) {
            return ['success' => true];
        }

        if (isset($data['order_no']) && isset($data['status']) && in_array($data['status'],['failed', 'expired'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $this->vector = $data['key2'];
        $url = $data['queryBalanceUrl'] . $data['merchant'] . '/balance';
        $post_data = [
            'timestamp' => now()->timestamp
        ];

        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $url, [
                'json' => $post_data
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['ok']) && $result['ok']) {
            $balance = $result['data']['balance'];

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
            if ($v != null && $v != "") {
                if ($v != end($data)) {
                    $signstr = $signstr . $v . "&";
                } else{
                    $signstr = $signstr . $v;
                }
            }
        }
        return base64_encode(openssl_encrypt($signstr, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, substr($this->vector, 0, 16)));
    }

    public function decrypt($data, $transaction)
    {
        $key = $transaction->thirdChannel->key;
        $vector = $transaction->thirdChannel->key2;
        $data = base64_decode($data);

        return openssl_decrypt(
        $data,
        'AES-256-CBC',
        $key,
        OPENSSL_RAW_DATA,
        substr($vector,0,16));
    }
}
