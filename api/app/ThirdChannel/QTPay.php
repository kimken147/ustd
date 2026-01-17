<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;
use GuzzleHttp\Client;

class QTPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'QTPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://awspayment.pcakasjt.com/api/payment/v1/shipping';
    public $xiafaUrl   = 'https://awspayment.pcakasjt.com/api/payment/v1/purchase';
    public $daifuUrl   = 'https://awspayment.pcakasjt.com/api/payment/v1/purchase';
    public $queryDepositUrl = 'https://awspayment.pcakasjt.com/api/payment/v1/query-shipping';
    public $queryDaifuUrl  = 'https://awspayment.pcakasjt.com/api/payment/v1/query-purchase';
    public $queryBalanceUrl = 'https://awspayment.pcakasjt.com/api/payment/v1/merchant-info';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'ok';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 1
    ];

    public $bankMap = [
        '山西银行' => 102,
        '石嘴山银行' => 98,
        '黄河农村商业银行' => 97,
        '昆仑银行' => 94,
        '新疆维吾尔自治区农村信用社联合' => 93,
        '曲靖兴福村镇银行' => 91,
        '红塔银行' => 90,
        '山西省农村信用社联合社' => 89,
        '内蒙古农村信用社' => 87,
        '天津滨海农村银行' => 86,
        '东营银行' => 83,
        '齐商银行' => 82,
        '厦门银行' => 80,
        '黑龙江省农村信用社' => 79,
        '张家口银行' => 77,
        '承德银行' => 74,
        '河南省农村信用社' => 73,
        '河北省农村信用社' => 72,
        '台州银行' => 65,
        '湖南省农村信用社' => 61,
        '中山农商银行' => 60,
        '兰州银行' => 57,
        '珠海华润银行' => 53,
        '广东华兴银行' => 52,
        '徽商銀行' => 44,
        '佛山农商银行' => 42,
        '广东农村信用社' => 41,
        '广州农村商业银行' => 39,
        '成都农村商业银行' => 38,
        '云南省农村信用社' => 36,
        '四川省农村信用社' => 35,
        '昆山农村商业银行' => 31,
        '福建省农村信用社' => 30,
        '中国农村信用社' => 29,
        '青岛农商银行' => 27,
        '北京农商银行' => 25,
        '浙江泰隆商业银行' => 24,
        '东亚银行' => 22,
        '中国邮政储蓄银行' => 4,
        '邮政储蓄银行' => 4,
        '邮政银行' => 4,
        '中国建设银行' => 3,
        '建设银行' => 3,
        '中国工商银行' => 2,
        '工商银行' => 2,
        '中国农业银行' => 1,
        '农业银行' => 1,
        '中国银行' => 5,
        '交通银行' => 7,
        '招商银行' => 6,
        '民生银行' => 12,
        '中国民生银行' => 12,
        '光大银行' => 9,
        '中国光大银行' => 9,
        '北京银行' => 16,
        '上海银行' => 18,
        '兴业银行' => 15,
        '平安银行' => 11,
        '浦发银行' => 8,
        '广发银行' => 14,
        '华夏银行' => 13,
        '深圳农村商业银行' => 63,
        '东莞银行' => 67,
        '东莞农村商业银行' => 66,
        '中信银行' => 10,
        '湖北农村信用社' => 68,
        '湖北银行' => 47,
        '江苏银行' => 81,
        '长安银行' => 56,
        '浙商银行' => 21,
        '南京银行' => 17,
        '恒丰银行' => 78,
        '长沙银行' => 49,
        '重庆农村商业银行' => 37,
        '广西农村信用社' => 33,
        '广西农村信用社联合社' => 33,
        '贵州省农村信用社' => 34,
        '贵州省农村信用社联合社' => 34,
        '海南省农村信用社联合社' => 76,
        '江苏省农村信用社联合社' => 43,
        '江西省农村信用社' => 32,
        '江西省农村信用社联合社' => 32,
        '山东省农村信用社' => 62,
        '山东省农村信用社联合社' => 62,
        '浙江省农村信用社联合社' => 28,
        '齐鲁银行' => 85,
        '晋商银行' => 101,
        '广州银行' => 45,
        '富滇银行' => 58,
        '贵阳银行' => 99,
        '江西银行' => 70,
        '贵州银行' => 71,
        '河北银行' => 75,
        '上海农商银行' => 26,
        '郑州银行' => 51,
        '广西北部湾银行' => 54,
        '桂林银行' => 55,
        '浙江稠州商业银行' => 92,
        '杭州银行' => 19,
        '宁波银行' => 20,
        '渤海银行' => 23,
        '武汉农村商业银行' => 59,
        '蒙商银行' => 88,
        '甘肃省农村信用社' => 95,
        '甘肃省农村信用社联合社' => 95,
        '甘肃银行' => 96,
        '莱商银行' => 100,
        '宁夏银行' => 84,
        '内蒙古银行' => 40,
        '海南银行' => 64,
        '汉口银行' => 48,
        '安徽省农村信用社' => 69,
        '华融湘江银行' => 50,
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $post = [
            'merchant_uuid'          => $data['merchant'] ?? $this->merchant,
            'currency_type'          => 1,
            'payment_type'           => $this->channelCodeMap[$this->channelCode],
            'order_amount'           => intval($data['request']->amount),
            'merchant_notify_url'    => $data['callback_url'],
            'merchant_order_id'      => $data['request']->order_number
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['order_user_real_name'] = $data['request']->real_name;
        }

        $post['signature'] = $this->makesign($post);
        //$post['backUrl'] = '';
        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $post
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['code']) && in_array($row['code'], ['SUCCESS'])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'      => $row['data']['redirect_url']
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
        $this->key = $data['key'];

        $post_data = [
            'order_amount'        => intval($data['request']->amount),
            'merchant_uuid'       => $data['merchant'] ?? $this->merchant,
            'merchant_order_id'   => $data['request']->order_number,
            'currency_type'       => 1,
            'merchant_notify_url' => $data['callback_url'],
            'bank_id'             => $this->bankMap[$data['request']->bank_name],
            'bank_province_name'  => $data['request']->bank_province ?? "空",
            'bank_city_name'      => $data['request']->bank_city ?? "空",
            'bank_account'        => $data['request']->bank_card_number,
            'bank_account_name'   => $data['request']->bank_card_holder_name,
        ];

        $post_data['signature'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post_data', 'row'));

        if (isset($row['code']) && in_array($row['code'], ['SUCCESS'])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'merchant_order_id'  => $data['request']->order_number,
            'merchant_uuid'      => $data['merchant'] ?? $this->merchant,
        ];
        $post_data['signature'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
                'json' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'post_data', 'response'));

        if (isset($row['code']) && in_array($row['code'], ['SUCCESS'])) {
            if (in_array($row['data']['status'], ['PROCESSING'])) {
                return ['success' => true, 'status' => Transaction::STATUS_PAYING];
            }
            if (in_array($row['data']['status'], ['SUCCESS'])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($row['data']['status'], ['FAILURE'])) {
                return ['success' => true, 'status' => Transaction::STATUS_FAILED];
            }
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['merchant_order_id'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'], ['SUCCESS'])) {
            return ['success' => true];
        }

        if (isset($data['status']) && !in_array($data['status'], ['SUCCESS'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant_uuid" => $data["merchant"],
        ];
        $postBody["signature"] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->request('post', $data['queryBalanceUrl'], [
                'json' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        $row = json_decode($response->getBody(), true);

        if ($row["code"] == "0") {
            $balance = $row["data"]["quota_amount"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }
        return 0;
    }

    public function makesign($data)
    {
        $data['secret'] = $this->key;
        ksort($data, SORT_REGULAR);
        $signstr = [];
        foreach (array_filter($data) as $k => $v) {
            if ($v != null && $v != "") {
                $signstr[] = $k . "=" . $v;
            }
        }
        return sha1(implode('&', $signstr));
    }
}
