<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class DouDoupay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'DouDoupay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api.doudoupays.com/gateway/pay/deposit';
    public $xiafaUrl   = 'https://api.doudoupays.com/gateway/pay/withdrawQuery';
    public $daifuUrl   = 'https://api.doudoupays.com/gateway/pay/withdraw';
    public $queryDepositUrl    = 'https://api.doudoupays.com/gateway/pay/withdrawQuery';
    public $queryDaifuUrl  = 'https://api.doudoupays.com/gateway/pay/withdrawQuery';
    public $queryBalanceUrl = 'https://api.doudoupays.com/gateway/pay/balance';

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
        Channel::CODE_BANK_CARD => "OTC",
        Channel::CODE_QR_ALIPAY => 'ALIPAY_QR'
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
        $postBody = [
            "mchCode" => $data["merchant"],
            'orderId' => $data['request']->order_number,
            'amount' => floatval($data['request']->amount),
            'paymode' => $data['key2'] ?: $this->channelCodeMap[$this->channelCode],
            'notifyUrl' => $data['callback_url'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['payerName'] = $data['request']->real_name;
            $postBody['user'] = $this->generateShortId($data['request']->real_name, 8);
        }

        $postBody["sign"] = $this->makesign([
            "mchCode" => $data["merchant"],
            'orderId' => $data['request']->order_number,
            'amount' => floatval($data['request']->amount),
            'paymode' => $data['key2'] ?: $this->channelCodeMap[$this->channelCode],
            'notifyUrl' => $data['callback_url'],
        ], $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody,
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class . " 代付", compact('data', 'postBody', 'message'));
            return ['success' => false, 'msg' => $e->getMessage()];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if ($row["retcode"] == 0) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row['payUrl'],
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ["success" => false, 'msg' => $row["retdesc"]];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        if (strtoupper($data['request']->bank_name) === 'TRC') {
            return ['success' => false, 'msg' => '不支持此銀行代付'];
        }

        $this->key = $data['key'];

        $postBody = [
            "mchCode" => $data["merchant"],
            'orderId' => $data['request']->order_number,
            'amount' => floatval($data['request']->amount),
            "currency" => "CNY",
            'cardId' => $data['request']->bank_card_number,
            'accountName' => $data['request']->bank_card_holder_name,
            "bankName" => $data['request']->bank_name,
            'notifyUrl' => $data['callback_url'],
        ];
        $postBody["sign"] = $this->makesign([
            "mchCode" => $data["merchant"],
            'orderId' => $data['request']->order_number,
            'amount' => floatval($data['request']->amount),
            'cardId' => $data['request']->bank_card_number,
            'accountName' => $data['request']->bank_card_holder_name,
            'notifyUrl' => $data['callback_url'],
        ], $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $body = json_decode($e->getResponse()->getBody(), true);
                Log::error(self::class, [
                    'data' => $data,
                    'response' => $body,
                ]);
                return ['success' => false, 'msg' => $body['retdesc'] ?? ''];
            }
            else {
                return ['success' => false, 'msg' => $e->getMessage()];
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, [
                'data' => $data,
                'message' => $message,
            ]);
            return ['success' => false, 'msg' => $e->getMessage()];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if (isset($row["retcode"]) && $row["retcode"] == 0) {
            return ["success" => true];
        }

        return ['success' => false, 'msg' => $row["retdesc"] ?? ''];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postBody = [
            "mchCode" => $data["merchant"],
            'orderId' => $data['request']->order_number,
        ];

        $postBody["sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
                'json' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'postBody', 'response'));

        if (isset($row['retcode']) && in_array($row['retcode'], [0])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign([
            "mchCode" => $data["mchCode"],
            "orderId" => $data["orderId"],
            "amount" => floatval($data["amount"])
        ], $thirdChannel->key);

        if ($sign != $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if (isset($data['orderId']) && $data['orderId'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '代付金额不正确'];
        }

        //代付检查状态
        if (isset($data['status']) && in_array($data["status"], [0])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (isset($data['status']) && in_array($data['status'], [1])) {
            return ['fail' => '逾时'];
        }

        //代收检查状态
        if (isset($data['completeTime'])) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "mchCode" => $data["merchant"],
            "currency" => "CNY"
        ];
        $postBody["sign"] = $this->makesign($postBody, $this->key);

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

        if ($row["retcode"] == 0) {
            $balance = $row["availableBalance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }
        return 0;
    }

    public function makesign($data, $key)
    {
        unset($data["sign"]);
        $strSign = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . $key;
        $sign = md5($strSign);
        return $sign;
    }

    private function generateShortId($string, $length = 6)
    {
        return substr(md5($string), 0, $length);
    }
}
