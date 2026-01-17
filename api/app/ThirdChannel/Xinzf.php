<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class Xinzf extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Xinzf';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api.xinzf.cc/api/v3/deposits';
    public $xiafaUrl   = 'https://api.xinzf.cc/api/payfor/trans';
    public $daifuUrl   = 'https://api.xinzf.cc/api/v3/transfers';
    public $queryDepositUrl    = 'https://api.xinzf.cc/api/pay/orderquery';
    public $queryDaifuUrl  = 'https://api.xinzf.cc/api/v3/transfers/query';
    public $queryBalanceUrl = 'https://api.xinzf.cc/api/v3/balance';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'ok';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => 'bank'
    ];

    public $bankMap = [
        '中国建设银行' => 'CCB',
        '中国农业银行' => 'ABC',
        '中国邮政储蓄银行' => 'PSBC',
        '中国光大银行' => 'CEB',
        '招商银行' => 'CMB',
        '交通银行' => 'BCM',
        '中信银行' => 'CNCB',
        '兴业银行' => 'CIB',
        '中国民生银行' => 'CMBC',
        '华夏银行' => 'HXB',
        '浦发银行' => 'SPDB',
        '广发银行' => 'CGB',
        '平安银行' => 'PAB',
        '北京银行' => 'BJBANK',
        '上海银行' => 'SHB',
        '南京银行' => 'NJCB',
        '渤海银行' => 'CBHB',
        '宁波银行' => 'NBCB',
        '杭州银行' => 'HZCB',
        '浙商银行' => 'CZB',
        '徽商银行' => 'HSBANK',
        '广州银行' => 'GCB',
        '长沙银行' => 'CSCB',
        '青岛银行' => 'QDCCB',
        '天津银行' => 'TCCB',
        '恒丰银行' => 'HFBANK',
        '浙江民泰商业银行' => 'MTBANK',
        '盛京银行' => 'SJBANK',
        '莱商银行' => 'LSBANK',
        '郑州银行' => 'ZZBANK',
        '厦门银行' => 'BOXM',
        '桂林银行' => 'GLBANK',
        '广西北部湾银行' => 'BOBBG',
        '浙江省农村信用社' => 'ZJRC',
        '重庆农村商业银行' => 'CRCBANK',
        '山东省农村信用社联合社' => 'SDRCU',
        '柳州银行' => 'LZCCB',
        '中原银行' => 'ZYBANK',
        '河南省农村信用社' => 'HNRCU',
        '四川天府银行' => 'PWEB',
        '福建省农村信用社联合社' => 'FJNX',
        '湖南省农村信用社联合社' => 'HUNNX',
        '湖北省农村信用社' => 'HBRCC',
        '张家口银行' => 'ZJKCCB',
        '晋城银行' => 'SHXJC',
        '广州农商银行' => 'GRCB',
        '东莞农商银行' => 'DRCB',
        '顺德农商银行' => 'SDEB',
        '广东农村信用社' => 'GDRC',
        '广西农村信用社' => 'GXRCU',
        '四川省农村信用社' => 'SCRCU',
        '重庆银行' => 'CQBANK',
        '贵州省农村信用社' => 'GZNX',
        '广东南粤银行' => 'GDNY',
        '辽宁省农村信用社' => 'INRCC',
        '吉林省农村信用社' => 'JLNLS',
        '吉林银行' => 'JLBANK',
        '江苏银行' => 'JSBC',
        '黑龙江省农村信用社' => 'HLJRCU',
        '中国银行' => 'BOC',
        '安徽省农村信用社' => 'ARCU',
        '广西农村信用社联合社' => 'GXRCU',
        '邯郸银行' => 'HDBANK',
        '建设银行' => 'CCB',
        '大连银行' => 'DLB',
        '河北省农村信用社' => 'HBNX',
        '富邦华一银行' => 'FUBON',
        '成都银行' => 'CDCB',
        '甘肃省农村信用' => 'GSNX',
        '江西银行' => 'NCB',
        '九江银行' => 'JJCCB',
        '江苏省农村信用社' => 'JSNX',
        '东莞银行' => 'BOD',
        '广东华兴银行' => 'GHB',
        '中国工商银行' => 'ICBC',
        '山西省农村信用社联合社' => 'SXRCU',
        '威海市商业银行' => 'WHCCB',
        '赣州银行' => 'GZB',
        '龙江银行' => 'LJBANK',
        '东莞农村商业银行' => 'DRCBCL',
        '台州银行' => 'TZBANK',
        '甘肃省农村信用社' => 'GSNX',
        '重庆农村商业银' => 'CRCBANK',
        '江西农商银行' => 'JXNXS',
        '齐商银行' => 'QSB',
        '保定银行' => 'BOB',
        '宁夏银行' => 'NXBANK',
        '西安银行' => 'XABANK',
        '内蒙古农村信用社' => 'NMGNXS',
        '锦州银行' => 'JZBANK',
        '丹东银行' => 'BODD',
        '广东省农村信用社' => 'GDRCU',
        '齐鲁银行' => 'QLBANK',
        '北京农商银行' => 'BJRCB',
        '沧州银行' => 'BOCZ',
        '鞍山银行' => 'BOAS',
        '绍兴银行' => 'SXCCB',
        '汉口银行' => 'HKB',
        '甘肃银行' => 'GSBANK',
        '民生银行' => 'CMBC',
        '上海农商银行' => 'SRCBANK',
        '云南省农村信用社' => 'YNRCC',
        '山西省农村信用社' => 'SXRCU',
        '光大银行' => 'CEB',
        '江苏省农村信用社联合社' => 'JSRCU',
        '长安银行' => 'CCABANK',
        '廊坊银行' => 'LCCB',
        '浙江泰隆商业银行' => 'ZJTLCB',
        '广州农业银行' => 'ABC',
        '武汉农村商业银行' => 'WHRCB',
        '湖北银行' => 'HBC',
        '深圳福田银座村镇银行' => 'FTYZB',
        '江西省农村信用社联合社' => 'JXRCU',
        '苏州银行' => 'BOSZ',
        '重庆三峡银行' => 'CCQTGB',
        '天津农商银行' => 'TRCB',
        '兰州银行' => 'LZYH',
        '浙江省农村信用社联合社' => 'ZJRC',
        '广东省农村信用社联合社' => 'GDRCC',
        '哈尔滨银行' => 'HRBANK',
        '新疆银行' => 'XJBANK',
        '海南省农村信用社' => 'HAINANBANK',
        '浙江稠州商业银行' => 'CZCB',
        '深圳农村商业银行' => 'SRCB',
        '富滇银行' => 'FDB',
        '山东省农村信用社' => 'SDRCU',
        '农村信用社' => 'HLJRCU',
        '西藏银行' => 'XZB',
        '贵阳银行' => 'BGY',
        '农商银行' => 'SDEB',
        '中银富登村镇银行' => 'BOCFCB',
        '温州银行' => 'WZCB',
        '营口银行' => 'BOYK',
        '贵州银行' => 'ZYCBANK',
        '金华银行' => 'JHCCB',
        '鄂尔多斯银行' => 'ORDOSB',
        '浙江网商银行' => 'MYBANK',
        '顺德农村商业银行' => 'SDEBANK',
        '乾县中银富登村镇银行' => 'BOCFCB',
        '广东顺德农村商业银行' => 'SDEBANK',
        '珠海华润银行' => 'CRBANK',
        '晋商银行' => 'JSHB',
        '河北银行' => 'HEBB',
        '邢台银行' => 'XTBANK',
        '支付宝' => 'ALIPAY',
        '北京中银富登村镇银行' => 'BOCFCB',
        '日照银行' => 'RZB',
        '常熟农商银行' => 'CSRCB',
        '江南农村商业银行' => 'JNRCB',
        '农村信用合作社' => 'ZJNX',
        '东营银行' => 'DYCCB',
        '厦门国际银行' => 'XIB',
    ];


    private function getHeaders(string $key) {
        return [
            'Authorization' => 'api-key ' . $key
        ];
    }

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $this->key2 = $data['key2'];
        $body = [
            "mid" => $data["merchant"],
            'amount' => $data['request']->amount,
            'order_no' => $data['request']->order_number,
            'gateway' => $data['key3'] ?? $this->channelCodeMap[$this->channelCode],
            'ip' => $data['request']->client_ip ?? $data['client_ip'] ?? '1.1.1.1',
            'notify_url' => $data['callback_url'],
            'name' => $data['request']->real_name ?? '隨機',
        ];


        try {
            $row = $this->sendRequest($data["url"], $body);
            $ret = [
                'pay_url'   => $row['url'] ?? '',
                'receiver_name' => $row['cardName'] ?? '',
                'receiver_bank_name' => $row["bankName"] ?? '',
                'receiver_account' => $row['cardNo'] ?? '',
                'receiver_bank_branch' => $row['bankBranch'] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        } catch (\Throwable $th) {
            return ["success" => false, 'msg' => $th->getMessage()];
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
        $this->key2 = $data['key2'];

        $bankCode = $this->bankMap[$data['request']->bank_name] ?: null;
        if (!$bankCode) {
            return ['success' => false, 'msg' => '不支持此銀行代付'];
        }

        $body = [
            'mid' => $data['merchant'],
            'amount' => $data['request']->amount,
            'order_no' => $data['request']->order_number,
            'ip' => $data['request']->client_ip ?? $data['client_ip'] ?? '1.1.1.1',
            'notify_url' => $data['callback_url'],
            'bank_code' => $bankCode,
            'card_no' => $data['request']->bank_card_number,
            'holder_name' => $data['request']->bank_card_holder_name,
        ];

        try {
            $row = $this->sendRequest($data['url'], $body);
            return ['success' => true, 'data' => $row];
        } catch (\Throwable $th) {
            return ["success" => false, 'msg' => $th->getMessage()];
        }
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $this->key2 = $data['key2'];

        $body = [
            'mid' => $data['merchant'],
            'order_no' => $data['request']->order_number,
        ];

        try {
            $row = $this->sendRequest($data['url'], $body);
            return ['success' => true, 'data' => $row];
        } catch (\Throwable $th) {
            return ["success" => false, 'msg' => $th->getMessage()];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $response = $request->all();
        $data = $response['data'];

        // if (!$this->verifySign($data, $thirdChannel->key2)) {
        //     return ["error" => "签名错误"];
        // }

        if ($data['order_no'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($data['amount'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data['status'] == 'succeeded') {
            return ['success' => true];
        }
        else if ($data['status'] == 'failed') {
            return ['fail' => $response['message'], 'msg' => $response['message']];
        }

        // //代付检查状态，失败
        // if (in_array($data->notify_type, ["payment_transfer_failed"])) {
        //     return ['fail' => '逾时'];
        // }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $body = [
            "mid" => $data["merchant"],
        ];

        try {
            $response = $this->sendRequest($data["queryBalanceUrl"], $body, false);
            $balance = $response["balance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            return 0;
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function sendRequest($url, array $data, $debug = true)
    {
        $data['sign'] = $this->makesign($data, $this->key2);
        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'headers' => $this->getHeaders($this->key),
                'json' => $data,
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['code'] !== 200) {
                throw new Exception($row['message'] ?? "未知錯誤");
            }

            return $row['data'];
        } catch (\Exception $e) {
            if ($debug) {
                Log::debug(self::class, compact('data', 'e'));
            }
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $e;
        }
    }


    public function makesign(array $body, string $key)
    {
        ksort($body);
        $string = '';
        foreach ($body as $k => $val) {
            if ($val == '' || $k == 'sign') {
                continue;
            }
            $string .= "{$k}={$val}&";
        }
        $string = trim($string, "&");
        return base64_encode(hash_hmac('sha1', $string, $key, true));
    }
}
