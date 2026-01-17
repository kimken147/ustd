<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;

class Jili extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Jili';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://cashwork.luck-pay.com/jili/gateway.do';
    public $xiafaUrl   = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/payfor/trans';
    public $daifuUrl   = 'https://cashwork.luck-pay.com/jili/ap.do';
    public $queryDepositUrl    = 'https://w9ju5db5gbnk2g1d.dsnft-ch.cn/api/pay/orderquery';
    public $queryDaifuUrl  = 'https://cashwork.luck-pay.com/jili/query.do';
    public $queryBalanceUrl = 'https://cashwork.luck-pay.com/jili/spQuery.do';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'SUCCESS';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => 30,
        Channel::CODE_QR_ALIPAY => 4,
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
        "上海农商银行" => "SRCB",
        "上海农商" => "SRCB",
        "浙商银行" => "CZBANK",
        "徽商银行" => "HSBANK",
        "广州银行" => "GZCB",
        "长沙银行" => "CSYH",
        "青岛银行" => "QDCCB",
        "天津银行" => "BANKOFTIANJIN",
        "成都农村商业银行" => "CDRCB",
        "成都农商银行" => "CDRCB",
        "成都农商" => "CDRCB",
        "泰隆银行" => "ZJTLCB",
        "盛京银行" => "SHENGJINGBANK",
        "郑州银行" => "ZZBANK",
        "上海浦东发展银行" => "SPDBANK",
        "浦发银行" => "SPDBANK",
        "厦门银行" => "XMCCB",
        "桂林银行" => "GUILINBANK",
        "广西北部湾银行" => "CORPORBANK",
        "浙江省农村信用社联合社" => "ZJ96596",
        "浙江省农村信用社" => "ZJ96596",
        "浙江省农信" => "ZJ96596",
        "浙江农村信用社联合社" => "ZJ96596",
        "浙江农村信用社" => "ZJ96596",
        "浙江农信" => "ZJ96596",
        "重庆农村商业银行" => "CQRCB",
        "重庆商业银行" => "CQRCB",
        "重庆农村商银" => "CQRCB",
        "重庆农商" => "CQRCB",
        "山东省农村信用社联合社" => "SDRCU",
        "山东省农村信用社" => "SDRCU",
        "山东省农信" => "SDRCU",
        "山东农村信用社联合社" => "SDRCU",
        "山东农村信用社" => "SDRCU",
        "山东农信" => "SDRCU",
        "柳州银行" => "LZCCB",
        "河南省农村信用社联合社" => "HNNX",
        "河南省农村信用社" => "HNNX",
        "河南省农信" => "HNNX",
        "河南农村信用社联合社" => "HNNX",
        "河南农村信用社" => "HNNX",
        "河南农信" => "HNNX",
        "四川天府银行" => "TFB",
        "广西壮族自治区农村信用社联合社" => "GX966888",
        "广西壮族自治区农村信用社" => "GX966888",
        "广西农村信用社" => "GX966888",
        "广西自治区农村信用社" => "GX966888",
        "福建省农村信用社联合社" => "FJNX",
        "福建省农村信用社" => "FJNX",
        "福建省农信" => "FJNX",
        "福建农村信用社联合社" => "FJNX",
        "福建农村信用社" => "FJNX",
        "福建农信" => "FJNX",
        "湖南省农村信用社联合社" => "HNNXS",
        "湖南省农村信用社" => "HNNXS",
        "湖南省农信" => "HNNXS",
        "湖南农村信用社联合社" => "HNNXS",
        "湖南农村信用社" => "HNNXS",
        "湖南农信" => "HNNXS",
        "安徽省农村信用社联合社" => "AHRCU",
        "安徽省农村信用社" => "AHRCU",
        "安徽省农信" => "AHRCU",
        "安徽农村信用社联合社" => "AHRCU",
        "安徽农村信用社" => "AHRCU",
        "安徽农信" => "AHRCU",
        "安徽信用社联合社" => "AHRCU",
        "安徽信用社" => "AHRCU",
        "广州省农村商业银行" => "GRCBANK",
        "广州农商银行" => "GRCBANK",
        "广州农商" => "GRCBANK",
        "东莞农村商业银行" => "DRCBANK",
        "东莞农商银行" => "DRCBANK",
        "东莞农商" => "DRCBANK",
        "深圳农村商业银行" => "4001961200",
        "深圳农商银行" => "4001961200",
        "深圳农商" => "4001961200",
        "顺德农村商业银行" => "SDEBANK",
        "顺德农商银行" => "SDEBANK",
        "顺德农商" => "SDEBANK",
        "广东省农村信用社联合社" => "GDRC",
        "广东省农村信用社" => "GDRC",
        "广东省农信" => "GDRC",
        "广东农村信用社联合社" => "GDRC",
        "广东农村信用社" => "GDRC",
        "广东农信" => "GDRC",
        "四川省农村信用社联合社" => "SCRCU",
        "四川省农村信用社" => "SCRCU",
        "四川省农信" => "SCRCU",
        "四川农村信用社联合社" => "SCRCU",
        "四川农村信用社" => "SCRCU",
        "四川农信" => "SCRCU",
        "云南省农村信用社联合社" => "YNRCC",
        "云南省农村信用社" => "YNRCC",
        "云南省农信" => "YNRCC",
        "云南农村信用社联合社" => "YNRCC",
        "云南农村信用社" => "YNRCC",
        "云南农信" => "YNRCC",
        "重庆银行" => "CQCBANK",
        "贵州省农村信用社联合社" => "GZNXBANK",
        "贵州省农村信用社" => "GZNXBANK",
        "贵州省农信" => "GZNXBANK",
        "贵州农村信用社联合社" => "GZNXBANK",
        "贵州农村信用社" => "GZNXBANK",
        "贵州农信" => "GZNXBANK",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $headers = [];
        $returnType = 4;

        $postBody = [
            "version" => "1.6",
            "cid" => $data["merchant"],
            'tradeNo' => $data['request']->order_number,
            'amount' => floatval($data['request']->amount) * 100,
            "bankCode" => "001",
            'payType' => $this->channelCodeMap[$this->channelCode],
            "acctName" => $data["request"]->real_name ?? "test",
            "acctId" => "123456789",
            "returnType" => $returnType,
            "requestTime" => date("YmdHis"),
            'notifyUrl' => $data['callback_url'],
            "orderContent" => "JSON"
        ];

        $sign = $this->makesign($postBody, $this->key);
        $postBody["sign"] = $sign;
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'headers' => $headers,
                'form_params' => $postBody
            ]);

            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class . " 代付", compact('data', 'postBody', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if ((isset($row["retcode"]) && $row["retcode"] == 0) || isset($row["redirectUrl"])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row['redirectUrl'] ?? '',
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
            "version" => "1.6",
            "cid" => $data["merchant"],
            'tradeNo' => $data['request']->order_number,
            'amount' => floatval($data['request']->amount) * 100,
            "payType" => "1",
            'acctName' => $data['request']->bank_card_holder_name,
            'acctNo' => $data['request']->bank_card_number,
            "bankCode" => $bankCode,
            'notifyUrl' => $data['callback_url'],
            "memo" => "JSON"
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

        if ($row["retcode"] == 0) {
            return ["success" => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $headers = [];
        $postBody = [
            "cid" => $data["merchant"],
            'tradeNo' => $data['request']->order_number,
            "type" => "003",
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

        if (isset($row['retcode']) && in_array($row['retcode'], ['0'])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($data["sign"] != $sign) {
            return ["error" => "簽名錯誤"];
        }

        if ($data['tradeNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if (floatval(Arr::first(explode(",", $data["amount"]))) != floatval($transaction->amount) * 100) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (isset($data['status']) && in_array($data['status'], [1])) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "cid" => $data["merchant"],
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

        if ($row["retcode"] == 0) {
            $balance = floatval($row["balance"]) / 100;
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }
        return 0;
    }

    public function makesign($data, $key)
    {
        ksort($data);
        unset($data["sign"]);
        // $data = array_filter($data, function ($v, $k) {
        //     return $v !== "" && !is_null($v);
        // }, ARRAY_FILTER_USE_BOTH);
        return md5(urldecode(http_build_query($data)) . "&key=$key");
    }
}
