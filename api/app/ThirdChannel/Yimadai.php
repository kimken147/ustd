<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;

class Yimadai extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Yimadai';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = '';
    public $xiafaUrl   = 'https://gwapi.yemadai.com/transfer/transferFixed';
    public $daifuUrl   = 'https://gwapi.yemadai.com/transfer/transferFixed';
    public $queryDepositUrl = '';
    public $queryDaifuUrl  = 'https://gwapi.yemadai.com/transfer/transferQueryFixed';
    public $queryBalanceUrl = 'https://gwapi.yemadai.com/checkBalance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'ok';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'bankcard'
    ];

    public $bankMap = [];

    public $errorMap = [
        'ERR1001' => '下发IP未绑定',
        'ERR1002' => 'xml格式错误',
        'ERR1003' => '签名错误',
        'ERR1004' => '最大转账笔数超过50笔或者小于1笔',
        'ERR1005' => '含有必要参数为空',
        'ERR1006' => 'Base64解析错误',
        'ERR1007' => '账户错误或者不存在此账户',
        'ERR1008' => '金额小于0',
        'ERR1009' => '金额错误',
        'ERR1010' => '余额不足',
        'ERR1011' => '系统异常',
        'ERR1012' => '订单号重复',
        'ERR1017' => '三方余额不足',
        'ERR1098' => '可操作额度不足，其中有部分余额必须进行提现。余额中必须有一定比例用于提现 风控规则',
        'ERR2001' => '开户名与卡号不匹配',
        'ERR2002' => '开户行与卡号不匹配',
        'ERR2003' => '省、市信息不匹配',
        'ERR2004' => '用户被风控（风险账户）',
        'ERR2005' => '用户被风控（银行卡对应的账户是黑名单）',
        'ERR2006' => '风控限制（22点至7点禁止代付操作）',
        'ERR6001' => '下发权限未开通（被风控）',
    ];

    public $queryErrorMap = [
        'ERR1001' => 'IP未绑定',
        'ERR1002' => 'Xml格式错误',
        'ERR1003' => '验签失败',
        'ERR1004' => '必要参数为空',
        'ERR1005' => 'Base64解析错误',
        'ERR1006' => '系统异常',
        'ERR1007' => '商户不存在',
        'ERR1008' => '查询订单不存在',
        'ERR2001' => '开户名与卡号不匹配',
        'ERR2002' => '开户行与卡号不匹配',
        'ERR2003' => '省、市信息不匹配',
        'ERR2004' => '用户被风控（风险账户）',
        'ERR2005' => '用户被风控（银行卡对应的账户是黑名单）',
        'ERR2006' => '风控限制（22点至7点禁止代付操作）',
        'ERR6001' => '下发权限未开通（被风控）'
    ];
    /*   代收   */
    public function sendDeposit($data)
    {

    }

    public function queryDeposit($data)
    {

    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split($data['key'], 64, "\n") . "-----END PUBLIC KEY-----\n");
        $this->key2 = openssl_pkey_get_private("-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split($data['key2'], 64, "\n") . "-----END RSA PRIVATE KEY-----\n");

        $signData = [
            'transId' => $data['request']->order_number,
            'bankCode' => $data['request']->bank_name,
            'provice' => (isset($data['request']->bank_province) && !empty($data['request']->bank_province)) ? $data['request']->bank_province : $data['request']->bank_name,
            'city' => (isset($data['request']->bank_city) && !empty($data['request']->bank_city)) ? $data['request']->bank_city : $data['request']->bank_name,
            'accountName' => $data['request']->bank_card_holder_name,
            'cardNo' => $data['request']->bank_card_number,
            'amount' =>  number_format($data['request']->amount, 2, '.', ''),
            'remark' => '代付',
        ];

        $signData['secureCode'] = $this->makeDaifuSign($signData, $data['merchant']);

        $postData = [
            'yemadai' => [
                'signType' => 'RSA',
                'tt' => '0',
                'notifyURL' => $data['callback_url'],
                'accountNumber' => $data['merchant'],

                'transferList' => $signData
            ]
        ];

        $transData = urlencode(base64_encode($this->getXml($postData)));

        try {
            $response = base64_decode($this->curl($data['url'], 'transData='.$transData, [], $data['proxy']));
            $result = simplexml_load_string($response);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postData', 'transData', 'message'));
            return ['success' => false, 'timeout' => true];
        }

        Log::debug(self::class, compact('data', 'postData', 'transData', 'response'));

        $code = (string)$result->errCode;
        $resCode = (string)$result->transferList->resCode;

        if (!empty($code) && in_array($code,['0000']) && (empty($resCode) || in_array($resCode, ['0000']))) {
            return ['success' => true];
        } elseif (!empty($code) && !in_array($code,['0000'])) {
            return ['success' => false, 'msg' => $this->errorMap[$code]];
        } elseif (!empty($resCode) && !in_array($resCode,['0000'])) {
            return ['success' => false, 'msg' => $this->errorMap[$resCode]];
        } else {
            return ['success' => false, 'msg' => ''];
        }
    }

    public function queryDaifu($data)
    {
        $this->key = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split($data['key'], 64, "\n") . "-----END PUBLIC KEY-----\n");
        $this->key2 = openssl_pkey_get_private("-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split($data['key2'], 64, "\n") . "-----END RSA PRIVATE KEY-----\n");

        $wrapData = [
            'signType' => 'RSA',
            'merchantNumber' => $data['merchant'],
            'mertransferID' => $data['request']->order_number,
            'requestTime' => now()->format('YmdHis')
        ];

        $wrapData['sign'] = $this->makeQuerySign($wrapData);

        $postData = [
            'yemadai' => $wrapData
        ];

        $requestDomain = urlencode(base64_encode($this->getXml($postData)));

        try {
            $response = base64_decode($this->curl($data['queryDaifuUrl'], 'requestDomain='.$requestDomain, [], $data['proxy']));
            $result = simplexml_load_string($response);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postData', 'requestDomain', 'message'));
            return ['success' => false, 'timeout' => true, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'postData', 'requestDomain', 'response'));

        $code = (string)$result->code;

        if (!empty($code) && in_array($code,['0000'])) {
            $state = (string)$result->transfer->state;
            if (in_array($state, ['00'])) {
                return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
            }
            if (in_array($state, ['11'])) {
                $memo = (string)$result->transfer->memo;
                if (!in_array($memo, array_keys($this->queryErrorMap))) {
                    return ['success' => true, 'status' => Transaction::STATUS_FAILED, 'msg' => $memo];
                } else {
                    return ['success' => true, 'status' => Transaction::STATUS_FAILED, 'msg' => $this->queryErrorMap[$memo]];
                }
            }
            if (in_array($state, ['22'])) {
                $memo = (string)$result->transfer->memo;
                return ['success' => true, 'msg' => $memo, 'status' => Transaction::STATUS_PAYING];
            }

            return ['success' => false, 'msg' => '', 'status' => Transaction::STATUS_PAYING];
        } else {
            $memo = (string)$result->transfer->memo;
            if (!empty($memo)) {
                if (in_array($memo, array_keys($this->queryErrorMap))) {
                    return ['success' => true, 'status' => Transaction::STATUS_FAILED, 'msg' => $this->queryErrorMap[$memo]];
                } else {
                    return ['success' => true, 'status' => Transaction::STATUS_PAYING, 'msg' => $memo];
                }
            }

            if (in_array($code, array_keys($this->queryErrorMap))) {
                return ['success' => true, 'status' => Transaction::STATUS_FAILED, 'msg' => $this->queryErrorMap[$code]];
            }

            return ['success' => false, 'msg' => '', 'status' => Transaction::STATUS_PAYING];
        }
    }

    public function queryBalance($data)
    {
        $this->key = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split($data['key'], 64, "\n") . "-----END PUBLIC KEY-----\n");
        $this->key2 = openssl_pkey_get_private("-----BEGIN RSA PRIVATE KEY-----\n" . chunk_split($data['key2'], 64, "\n") . "-----END RSA PRIVATE KEY-----\n");

        $wrapData = [
            'MerNo' => $data['merchant'],
            'RequestTime' => now()->format('YmdHis'),
        ];
        $wrapData['SignInfo'] = $this->makeBalanceSign($wrapData);

        $postData = [
            'CheckBalanceRequest' => $wrapData
        ];

        $requestDomain = urlencode(base64_encode($this->getXml($postData)));

        try {
            $response = $this->curl($data['queryBalanceUrl'], 'requestDomain='.$requestDomain, [], $data['proxy']);
            $result = simplexml_load_string($response);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postData', 'requestDomain', 'message'));
            return ['success' => false, 'timeout' => true];
        }

        Log::debug(self::class, compact('data', 'postData', 'requestDomain', 'response'));

        $code = (string)$result->respCode;

        if(!empty($code) && in_array($code,['0000'])){
            $balance = (string)$result->availableBalance;
            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);
            return $balance;
        } else {
            return 0;
        }
    }

    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['MerNo'] != $transaction->thirdChannel->merchant_id) {
            return ['error' => '订单编号不正确'];
        }

        if ($data['MerBillNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //檢查金額
        if ($data['Amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        //檢查狀態
        if (isset($data['Succeed']) && in_array($data['Succeed'], ['00'])) {
            return ['success' => true];
        }

        if (isset($data['Succeed']) && in_array($data['Succeed'], ['11'])) {
            return ['fail' => $data['Result']];
        }

        return ['error' => '未知错误'];
    }

    public function makeDaifuSign($data, $merchantId)
    {
        $str = 'transId=' . $data['transId'] . '&accountNumber=' . $merchantId . '&cardNo=' . $data['cardNo'] . '&amount=' . $data['amount'];
        return openssl_sign($str, $sign, $this->key2, OPENSSL_ALGO_SHA1) ? base64_encode($sign) : false;
    }

    public function makeQuerySign($data)
    {
        $str = $data['merchantNumber'] . '&' . $data['requestTime'];
        return openssl_sign($str, $sign, $this->key2, OPENSSL_ALGO_SHA1) ? base64_encode($sign) : false;
    }

    public function makeBalanceSign($data)
    {
        $str = $data['MerNo'] . $data['RequestTime'];
        return openssl_sign($str, $sign, $this->key2, OPENSSL_ALGO_SHA1) ? base64_encode($sign) : false;
    }

    private function getXml($data, $root = true)
    {
        $str = "";
        if ($root) $str .= '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        foreach ($data as $key => $val) {
            if (is_array($val)) {
                $child = self::getXml($val, false);
                $str .= "<$key>$child</$key>";
            } else {
                $str .= "<$key>$val</$key>";
            }
        }
        return $str;
    }
}