<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;
use App\Utils\BCMathUtil;
use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;


class LeliPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'LeliPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://paygate.lelipay.com:9043/lelipay-gateway-onl/txn';
    public $xiafaUrl   = 'https://paygate.lelipay.com:9043/lelipay-gateway-onl/txn';
    public $daifuUrl   = 'https://paygate.lelipay.com:9043/lelipay-gateway-onl/txn';
    public $queryDepositUrl = 'https://paygate.lelipay.com:9043/lelipay-gateway-onl/txn';
    public $queryDaifuUrl  = 'https://paygate.lelipay.com:9043/lelipay-gateway-onl/txn';
    public $queryBalanceUrl = 'https://paygate.lelipay.com:9043/lelipay-gateway-onl/txn';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = '200';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 21
    ];

    public $bankMap = [
        '工商银行' => '01020000',
        "中国工商银行" => "01020000",
        '农业银行' => '01030000',
        "中国农业银行" => '01030000',
        '中国银行' => '01040000',
        '建设银行' => '01050000',
        "中国建设银行" => "01050000",
        '交通银行' => '03010000',
        '中信银行' => '03020000',
        '光大银行' => '03030000',
        "中国光大银行" => '03030000',
        '华夏银行' => '03040000',
        '民生银行' => '03050000',
        "中国民生银行" => '03050000',
        '广发银行' => '03060000',
        '平安银行' => '03070000',
        '招商银行' => '03080000',
        '兴业银行' => '03090000',
        '浦发银行' => '03100000',
        '恒丰银行' => '03110000',
        '上海银行' => '03130000',
        '北京银行' => '03131000',
        '南京银行' => '03133201',
        '杭州银行' => '03133301',
        '浙商银行' => '03160000',
        '北京农村商业银行' => '04020011',
        '上海农商银行' => '04020031',
        "上海农村商业银行" => '04020031',
        '厦门银行' => '04023930',
        '邮储银行' => '04030000',
        "中国邮政储蓄银行" => '04030000',
        '福建海峡银行' => '04053910',
        '宁波银行' => '04083320',
        '广州银行' => '04135810',
        '汉口银行' => '04145210',
        '大连银行' => '04202220',
        '苏州银行' => '04213050',
        '东莞银行' => '04256020',
        '天津银行' => '04341100',
        '宁夏银行' => '04369800',
        '锦州银行' => '04392270',
        '徽商银行' => '04403600',
        '重庆银行' => '04416530',
        '哈尔滨银行' => '04422610',
        '兰州银行' => '04478210',
        '江西银行' => '04484210',
        '吉林银行' => '04512420',
        '九江银行' => '04544240',
        '台州银行' => '04593450',
        '潍坊银行' => '04624580',
        '泉州银行' => '04643970',
        '嘉兴银行' => '04703350',
        '廊坊银行' => '04721460',
        '浙江泰隆商业银行' => '04733450',
        '湖州银行' => '04753360',
        '包商银行' => '04791920',
        '桂林银行' => '04916170',
        '柳州银行' => '04956140',
        '江苏银行' => '05083000',
        '重庆三峡银行' => '05426900',
        '晋中银行' => '05591750',
        '宁波通商银行' => '05803320',
        '江苏银行' => '05083000',
        '邯郸市商业银行' => '05171270',
        '昆山农信社' => '14023052',
        '江苏省农村信用社联合社' => '14243000',
        '吴江农商行' => '14283054',
        '浙江省农村信用社' => '14293300',
        '广西农村信用社' => '14436100',
        '吉林农村信用社' => '14452400',
        '安徽省农村信用社联合社' => '14473600',
        '海南省农村信用社' => '14486400',
        '重庆农村商业银行' => '15136900',
        '富滇银行' => '64667310',
        '广东南粤银行' => '64895910',
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $math = new BCMathUtil;
        $post = [
            'txnType'        => '01',
            'txnSubType'    => $this->channelCodeMap[$this->channelCode],
            'secpVer'       => 'icp3-1.1',
            'secpMode'      => 'perm',
            'orderDate'     => now()->format('Ymd'),
            'orderTime'     => now()->format('His'),
            'merId'         => intval($data['merchant']) ?? intval($this->merchant),
            'orderId'       => $data['request']->order_number,
            'payerId'       => '',
            'pageReturnUrl' => 'https://www.baidu.com/',
            'productTitle'  => '充值',
            'timeStamp'     => now()->format('YmdHis'),
            'macKeyId'      => intval($data['merchant']) ?? intval($this->merchant),
            'currencyCode'  => 156,
            'txnAmt'        => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分
            'notifyUrl'     => $data['callback_url'],
            'sthtml'        => 1,
            'cardType'      => 'DT01',
            'bankNum'       => '01020000'
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['accName'] = $data['request']->real_name;
        }

        $post['mac'] = $this->makesign($post);

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

        if (isset($row['respCode']) && in_array($row['respCode'], ['0000'])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'      => $row['extInfo']
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ['success' => false];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $math = new BCMathUtil;

        $this->key = $data['key'];

        $post_data = [
            'txnType'       => '52',
            'txnSubType'    => '10',
            'secpVer'       => 'icp3-1.1',
            'secpMode'      => 'perm',
            'orderDate'     => now()->format('Ymd'),
            'orderTime'     => now()->format('His'),
            'merId'         => intval($data['merchant']) ?? intval($this->merchant),
            'orderId'       => $data['request']->order_number,
            'productTitle'  => '充值',
            'timeStamp'     => now()->format('YmdHis'),
            'macKeyId'      => intval($data['merchant']) ?? intval($this->merchant),
            'currencyCode'  => 156,
            'txnAmt'        => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分
            'notifyUrl'     => $data['callback_url'],
            'bankNum'       => $this->bankMap[$data['request']->bank_name],
            'bankName'      => $data['request']->bank_name,
            'accName'  => $data['request']->bank_card_holder_name,
            'accNum'   => $data['request']->bank_card_number
        ];

        $post_data['mac'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'form_params' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post_data', 'row'));

        if (isset($row['respCode']) && in_array($row['respCode'], ['0000'])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'txnType'        => '00',
            'txnSubType'    => '50',
            'secpVer'       => 'icp3-1.1',
            'secpMode'      => 'perm',
            'orderDate'     => now()->format('Ymd'),
            'timeStamp'     => now()->format('YmdHis'),
            'orderId'       => $data['request']->order_number,
            'merId'         => intval($data['merchant']) ?? intval($this->merchant),
            'macKeyId'      => intval($data['merchant']) ?? intval($this->merchant)
        ];
        $post_data['mac'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
                'form_params' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'post_data', 'response'));

        if (isset($row['respCode']) && in_array($row['respCode'], ['0000'])) {
            if (in_array($row['txnStatus'], ['01'])) {
                return ['success' => true, 'status' => Transaction::STATUS_PAYING];
            }
            if (in_array($row['txnStatus'], ['10'])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($row['txnStatus'], ['20'])) {
                return ['success' => true, 'status' => Transaction::STATUS_FAILED];
            }
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();
        $math = new BCMathUtil;

        if ($data['orderId'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['txnAmt']) && $data['txnAmt'] != $math->mul($transaction->amount, 100, 0)) {   // 金額單位是分
            return ['error' => '金额不正确'];
        }

        if (isset($data['txnStatus']) && in_array($data['txnStatus'], ['10'])) {
            return ['success' => true];
        }

        if (isset($data['txnStatus']) && in_array($data['txnStatus'], ['20'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];

        $math = new BCMathUtil;

        $post_data = [
            'txnType'        => '00',
            'txnSubType'    => '90',
            'secpVer'       => 'icp3-1.1',
            'secpMode'      => 'perm',
            'merId'         => intval($data['merchant']) ?? intval($this->merchant),
            'macKeyId'      => intval($data['merchant']) ?? intval($this->merchant),
            'timeStamp'     => now()->format('YmdHis'),
            'accCat'        => '00'
        ];

        $post_data['mac'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryBalanceUrl'], [
                'form_params' => $post_data
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        // Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['respCode']) && in_array($result['respCode'], ['0000'])) {
            $balance = $math->div($result['balance'], 100, 2);

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

            $signstr = $signstr . $k . "=" . $v . "&";
        }
        return md5($signstr . "k=" . $this->key);
    }
}
