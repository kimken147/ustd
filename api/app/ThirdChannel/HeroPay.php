<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Utils\BCMathUtil;
use App\Models\ThirdChannel as ThirdChannelModel;
use App\Models\Transaction;
use Illuminate\Support\Carbon;

class HeroPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'HeroPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://pay.heropaycn.com/api/pay/create_order';
    public $xiafaUrl   = 'https://withdraw.heropaycn.com/api/trans/create_order';
    public $daifuUrl   = 'https://withdraw.heropaycn.com/api/trans/create_order';
    public $queryDepositUrl = 'https://inquiry.heropaycn.com/api/pay/query_order';
    public $queryDaifuUrl  = 'https://inquiry.heropaycn.com/api/trans/query_order';
    public $queryBalanceUrl = 'https://inquiry.heropaycn.com/api/query/query_balance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 8019
    ];

    public $bankMap = [
        '中国银行' => 'HPT00001',
        '工商银行' => 'HPT00002',
        '建设银行' => 'HPT00003',
        '农业银行' => 'HPT00004',
        '招商银行' => 'HPT00005',
        '交通银行' => 'HPT00006',
        '中信银行' => 'HPT00007',
        '兴业银行' => 'HPT00008',
        '民生银行' => 'HPT00009',
        '华夏银行' => 'HPT00010',
        '浦发银行' => 'HPT00011',
        '汇丰银行' => 'HPT00012',
        '渣打银行' => 'HPT00013',
        '花旗银行' => 'HPT00014',
        '德意志银行' => 'HPT00015',
        '瑞士银行' => 'HPT00016',
        '荷兰银行' => 'HPT00017',
        '香港汇丰' => 'HPT00018',
        '香港花旗' => 'HPT00019',
        '香港东亚银行' => 'HPT00020',
        '恒生银行' => 'HPT00021',
        '光大银行' => 'HPT00022',
        '广发银行' => 'HPT00023',
        '平安银行' => 'HPT00024',
        '北京银行' => 'HPT00025',
        '邮政银行' => 'HPT00026',
        '中国邮政储蓄银行' => 'HPT00026',
        '上海银行' => 'HPT00027',
        '南京银行' => 'HPT00028',
        '渤海银行' => 'HPT00029',
        '宁波银行' => 'HPT00030',
        '深圳发展银行' => 'HPT00031',
        '北京农商银行' => 'HPT00032',
        '上海农商银行' => 'HPT00033',
        '浙江稠州商业银行' => 'HPT00034',
        '杭州银行' => 'HPT00035',
        '富滇银行' => 'HPT00036',
        '浙商银行' => 'HPT00037',
        '河北银行' => 'HPT00038',
        '徽商银行' => 'HPT00039',
        '人民银行' => 'HPT00040',
        '广州银行' => 'HPT00041',
        '广西农村信用社' => 'HPT00042',
        '广西农村信用社联合社' => 'HPT00042',
        '广东省农村信用社' => 'HPT00043',
        '广东省农村信用社联合社' => 'HPT00043',
        '广州农商银行' => 'HPT00044',
        '江苏银行' => 'HPT00045',
        '福建农村信用社' => 'HPT00046',
        '福建省农村信用社' => 'HPT00046',
        '柳州银行' => 'HPT00047',
        '山东银行' => 'HPT00048',
        '浙江农村信用社联合社' => 'HPT00049',
        '浙江省农村信用社联合社' => 'HPT00049',
        '湖南农村信用社' => 'HPT00050',
        '湖南省农村信用社' => 'HPT00050',
        '广西北部湾银行' => 'HPT00051',
        '华润银行' => 'HPT00052',
        '吉林银行' => 'HPT00053',
        '海南农村信用社' => 'HPT00054',
        '海南省农村信用社' => 'HPT00054',
        '海南省农村信用社联合社' => 'HPT00054',
        '九江银行' => 'HPT00055',
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $math = new BCMathUtil;
        $code = $data["key4"] ?? $this->channelCodeMap[$this->channelCode];
        $post = [
            'mchId'      => intval($data['merchant']) ?? intval($this->merchant),
            'appId'      => $data['key2'],
            'productId'  => $code,
            'amount'     => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分
            'currency'   => 'cny',
            'clientIp'   => $data['request']->client_ip ?? $data['client_ip'],
            'notifyUrl'  => $data['callback_url'],
            'mchOrderNo' => $data['request']->order_number,
            'subject'    => '商品名称',
            'body'       => '商品描述'
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['param2'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makesign($post);
        $params = json_encode($post);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->depositUrl, [
                'headers' => $postHeaders,
                'form_params' => [
                    'params' => $params
                ]
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false, 'msg' => $message];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['retCode']) && in_array($row['retCode'], ['SUCCESS'])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'   => $row['payParams']['payUrl'],
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ['success' => false, "msg" => $row["retMsg"] ?? ""];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $math = new BCMathUtil;
        $post_data = [
            'mchId'            => intval($data['merchant']) ?? intval($this->merchant),
            'appId'            => $data['key2'],
            'amount'           => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分
            'currency'         => 'cny',
            'mchTransOrderNo'  => $data['request']->order_number,
            'notifyUrl'        => $data['callback_url'],
            'accountName'      => $data['request']->bank_card_holder_name,
            'accountNo'        => $data['request']->bank_card_number,
            'accountType'      => 1,
            'bankName'         => $data['request']->bank_name,
            'bankCode'         => $this->bankMap[$data['request']->bank_name],
            'province'         => $data['request']->bank_province ?? '无',
            'city'             => $data['request']->bank_city ?? '无',
            'param2'           => $data['key3']
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $params = json_encode($post_data);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->daifuUrl, [
                'headers' => $postHeaders,
                'form_params' => [
                    'params' => $params
                ]
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'msg' => $message];
        }

        Log::debug(self::class, compact('data', 'post_data', 'row'));

        if (isset($row['retCode']) && in_array($row['retCode'], ['SUCCESS'])) {
            return ['success' => true];
        }

        return ['success' => false, "msg" => $row["retMsg"] ?? ""];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'mchTransOrderNo' => $data['request']->order_number,
            'mchId'           => intval($data['merchant']) ?? intval($this->merchant),
            'appId'           => $data['key2'],
        ];
        $post_data['sign'] = $this->makesign($post_data);
        $params = json_encode($post_data);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->queryDaifuUrl, [
                'headers' => $postHeaders,
                'form_params' => [
                    'params' => $params
                ]
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'post_data', 'response'));

        if (isset($row['retCode']) && in_array($row['retCode'], ['SUCCESS'])) {
            if (in_array($row['status'], [1])) {
                return ['success' => true, 'status' => Transaction::STATUS_PAYING];
            }
            if (in_array($row['status'], [2])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($row['status'], [3])) {
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

        if (isset($data['mchOrderNo']) && $data['mchOrderNo'] != $transaction->order_number) {
            return ['error' => '代收订单编号不正确'];
        }

        if (isset($data['mchTransOrderNo']) && $data['mchTransOrderNo'] != $transaction->order_number) {
            return ['error' => '代付订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $math->mul($transaction->amount, 100, 0)) {   // 金額單位是分
            return ['error' => '金额不正确'];
        }

        if (isset($data['mchOrderNo']) && isset($data['status']) && in_array($data['status'], [2, 3])) {
            return ['success' => true];
        }

        if (isset($data['mchTransOrderNo']) && isset($data['status']) && in_array($data['status'], [2])) {
            return ['success' => true];
        }

        if (isset($data['mchTransOrderNo']) && isset($data['status']) && in_array($data['status'], [3])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $math = new BCMathUtil;
        $post_data = [
            'mchId' => $data['merchant'],
            'queryTime' => now()->timestamp
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $post_data = json_encode($post_data);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $this->queryBalanceUrl, [
                'headers' => $postHeaders,
                'form_params' => [
                    'params' => $post_data
                ]
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        if (isset($result['retCode']) && in_array($result['retCode'], ['SUCCESS'])) {
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
            if ($v != null && $v != "") {
                $signstr = $signstr . $k . "=" . $v . "&";
            }
        }
        return strtoupper(md5($signstr . "key=" . $this->key));
    }
}
