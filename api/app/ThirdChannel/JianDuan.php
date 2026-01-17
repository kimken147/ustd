<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class JianDuan extends ThirdChannel
{
    //Log名称
    public $log_name   = 'JianDuan';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/pay/orderadd';
    public $xiafaUrl   = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/payfor/trans';
    public $daifuUrl   = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/pay/dfadd';
    public $queryDepositUrl    = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/pay/orderquery';
    public $queryDaifuUrl  = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/pay/payquery';
    public $queryBalanceUrl = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/pay/balance';

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
        Channel::CODE_BANK_CARD => 1,
        Channel::CODE_QR_ALIPAY => 3,
        Channel::CODE_ZH_ALIPAY => 4,
        Channel::CODE_UNION_QUICK_PASS => 1
    ];

    public $bankMap = [
        "中国工商银行" => "ICBC",
        "工商银行" => "ICBC",
        "中国建设银行" => "CCB",
        "中国建设" => "CCB",
        "建设银行" => "CCB",
        "中国农业银行" => "ABCHINA",
        "农业银行" => "ABCHINA",
        "中国邮政储蓄银行" => "PSBC",
        "邮政银行" => "PSBC",
        "中国邮政" => "PSBC",
        "中国光大银行" => "ChinaEverbrightBank",
        "光大银行" => "HPTChinaEverbrightBank00022",
        "招商银行" => "CMBCHINA",
        "交通银行" => "BANKCOMM",
        "中信银行" => "CHINACITICBANK",
        "兴业银行" => "CIB",
        "中国银行" => "BANKOFCHINA",
        "中国民生银行" => "CMBC",
        "民生银行" => "CMBC",
        "华夏银行" => "HUAXIABANK",
        "广发银行" => "CGB",
        "平安银行" => "PINGANBANK",
        "北京银行" => "BEIJING",
        "上海银行" => "BANKOFSHANGHAI",
        "南京银行" => "NJCB",
        "渤海银行" => "CBHB",
        "宁波银行" => "NBCB",
        "上海农村商业银行" => "SRCB",
        "浙商银行" => "CZBANK",
        "徽商银行" => "HSBANK",
        "广州银行" => "GZCB",
        "长沙银行" => "CSYH",
        "青岛银行" => "QDCCB",
        "天津银行" => "BANKOFTIANJIN",
        "成都农村商业银行" => "CDRCB",
        "泰隆银行" => "ZJTLCB",
        "盛京银行" => "SHENGJINGBANK",
        "郑州银行" => "ZZBANK",
        "上海浦东发展银行" => "SPDBANK",
        "浦发银行" => "SPDBANK",
        "厦门银行" => "XMCCB",
        "桂林银行" => "GUILINBANK",
        "广西北部湾银行" => "CORPORBANK",
        "浙江省农村信用社" => "ZJ96596",
        "浙江农信" => "ZJ96596",
        "重庆农村商业银行" => "CQRCB",
        "山东省农村信用社联合社" => "SDRCU",
        "山东农村信用社" => "SDRCU",
        "柳州银行" => "LZCCB",
        "河南省农村信用社" => "HNNX",
        "四川天府银行" => "TFB",
        "广西壮族自治区农村信用社联合社" => "GX966888",
        "广西农村信用社" => "GX966888",
        "广西自治区农村信用社" => "GX966888",
        "福建省农村信用社联合社" => "FJNX",
        "福建省农村信用社" => "FJNX",
        "湖南省农村信用社联合社" => "HNNXS",
        "湖南省农村信用社" => "HNNXS",
        "安徽信用社" => "AHRCU",
        "广州农商银行" => "GRCBANK",
        "广州省农村商业银行" => "GRCBANK",
        "东莞农商银行" => "DRCBANK",
        "东莞农商" => "DRCBANK",
        "深圳农商银行" => "4001961200",
        "深圳农村商业银行" => "4001961200",
        "顺德农商银行" => "SDEBANK",
        "广东农村信用社" => "GDRC",
        "四川省农村信用社" => "SCRCU",
        "云南农村信用社" => "YNRCC",
        "云南省农村信用社联合社" => "YNRCC",
        "重庆银行" => "CQCBANK",
        "贵州省农村信用社" => "GZNXBANK",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $headers = [];
        $postBody = [
            "merNo" => $data["merchant"],
            'ordernum' => $data['request']->order_number,
            'bankCode' => $this->channelCodeMap[$this->channelCode],
            'amount' => $data['request']->amount,
            'notifyurl' => $data['callback_url'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['playerName'] = $data['request']->real_name;
        }

        $postBody["sign"] = $this->makesign($postBody, $this->key);
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                "headers" => $headers,
                'form_params' => $postBody,
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class . " 代付", compact('data', 'postBody', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if ($row["status"] == "success") {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row['payurl'] ?? '',
                'receiver_name' => $row["name"],
                'receiver_bank_name' => $row["bankname"],
                'receiver_account' => $row["bankcard"],
                'receiver_bank_branch' => $row["subbank"],
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ["success" => false];
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
        $bankCode = $this->bankMap[$data['request']->bank_name];

        if (!$bankCode) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $postBody = [
            "merNo" => $data["merchant"],
            'ordernum' => $data['request']->order_number,
            'bankname' => $data["request"]->bank_name,
            "bankCode" => $bankCode,
            'bankcard' => $data['request']->bank_card_number,
            'amount' => $data['request']->amount,
            "subbank" => "",
            'name' => $data['request']->bank_card_holder_name,
            'notifyurl' => $data['callback_url'],
        ];
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $postBody["sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'headers' => $headers,
                'form_params' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if (isset($row["status"]) && $row["status"] == "success") {
            return ["success" => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $headers = [];
        $postBody = [
            "merNo" => $data["merchant"],
            'ordernum' => $data['request']->order_number,
        ];

        $postBody["sign"] = $this->makesign($postBody, $this->key);
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
                'headers' => $headers,
                'form_params' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'postBody', 'response'));

        if (isset($row['paystatus']) && in_array($row['paystatus'], ['0', "1"])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($sign != $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if (isset($data['money']) && $data['ordernum'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if (isset($data['money']) && $data['money'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代付检查金额
        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '代付金额不正确'];
        }

        //代收检查状态
        if (isset($data['status']) && in_array($data['status'], [0, 1])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (isset($data['status']) && in_array($data['status'], [2, 3])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merNo" => $data["merchant"],
            "datetime" => date("YmdHis"),
        ];
        $postBody["sign"] = $this->makesign($postBody, $this->key);
        $headers = [];
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new Client();
            $response = $client->request('POST', $data['queryBalanceUrl'], [
                'headers' => $headers,
                'form_params' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        $row = json_decode($response->getBody(), true);

        if ($row["status"] == "success") {
            $balance = $row["balance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }
        return 0;
    }

    public function makesign($body, $key)
    {
        $filterBody = array_filter($body, function ($k) {
            return in_array($k, ["merNo", "ordernum", "bankname", "datetime", "amount"]);
        },  ARRAY_FILTER_USE_KEY);
        $strSign = array_reduce($filterBody, function ($prev, $cur) use ($filterBody) {
            return $prev . $cur;
        }, "") . $key;
        return md5($strSign);
    }
}
