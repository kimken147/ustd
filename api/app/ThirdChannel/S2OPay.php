<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class S2OPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'S2OPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://api.s2opay.com/api/payment/deposit';
    public $xiafaUrl = 'https://mwifuswzv.com/api/payfor/trans';
    public $daifuUrl = 'https://api.s2opay.com/api/charge/receive';
    public $queryDepositUrl = 'https://mwifuswzv.com/api/pay/orderquery';
    public $queryDaifuUrl = 'https://api.s2opay.com/api/charge/info';
    public $queryBalanceUrl = 'https://api.s2opay.com/api/inquire/account/info';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "webbk_real_name",
        Channel::CODE_ALIPAY_VM => 'alipay_qr',
        Channel::CODE_QR_ALIPAY => 'alipay_alipay_real_name',
        Channel::CODE_QR_WECHATPAY => 'weixin_qr'
    ];

    public $bankCodeMap = [
        // 主要銀行及其別名
        '中国银行' => 'BKCHCNBJ',
        '中国工商银行' => 'ICBKCNBJ',
        '工商银行' => 'ICBKCNBJ',
        '中国建设银行' => 'PCBCCNBJ',
        '中国建设' => 'PCBCCNBJ',
        '建设银行' => 'PCBCCNBJ',
        '中国农业银行' => 'ABOCCNBJ',
        '农业银行' => 'ABOCCNBJ',
        '中国邮政储蓄银行' => 'PSBCCNBJ',
        '邮政银行' => 'PSBCCNBJ',
        '中国邮政' => 'PSBCCNBJ',
        '中国光大银行' => 'EVERCNBJ',
        '光大银行' => 'EVERCNBJ',
        '招商银行' => 'CMBCCNBS',
        '交通银行' => 'COMMCNSH',
        '中信银行' => 'CIBKCNBJ',
        '兴业银行' => 'FJIBCNBA',
        '中国民生银行' => 'MSBCCNBJ',
        '民生银行' => 'MSBCCNBJ',
        '华夏银行' => 'HXBKCNBJ',
        '广发银行' => 'GDBKCN22',
        '平安银行' => 'SZDBCNBS',

        // 其他全国性銀行
        '北京银行' => 'BJCNCNBJ',
        '上海银行' => 'BOSHCNSH',
        '南京银行' => 'NJCBCNBN',
        '渤海银行' => 'CHBHCNBT',
        '宁波银行' => 'BKNBCN2N',
        '上海农村商业银行' => 'SHRCCNSH',
        '上海农商银行' => 'SHRCCNSH',
        '浙商银行' => 'ZJCBCN2N',
        '徽商银行' => 'HFCBCNSH',
        '广州银行' => 'GZCBCN22',
        '长沙银行' => 'CHCCCNSS',
        '青岛银行' => 'QCCBCNBQ',
        '天津银行' => 'TCCBCNBT',
        '成都农村商业银行' => 'CDRCB',
        '泰隆银行' => 'ZJTLCNBH',
        '盛京银行' => 'SYCBCNBY',
        '郑州银行' => 'ZZBKCNBZ',
        '上海浦东发展银行' => 'SPDBCNSH',
        '浦发银行' => 'SPDBCNSH',

        // 其他地方性銀行
        '厦门银行' => 'CBXMCNBA',
        '桂林银行' => 'GLBKCNBG',
        '广西北部湾银行' => 'BGBKCNBJ',
        '柳州银行' => 'LZBKCNBJ',
        '四川天府银行' => 'TFB',

        // 農村信用社系統
        '浙江省农村信用社' => 'ZJ96596',
        '浙江农信' => 'ZJ96596',
        '山东省农村信用社联合社' => 'SDRCU',
        '山东农村信用社' => 'SDRCU',
        '河南省农村信用社' => 'HNRCU',
        '广西壮族自治区农村信用社联合社' => 'GX966888',
        '广西农村信用社' => 'GX966888',
        '广西自治区农村信用社' => 'GX966888',
        '福建省农村信用社联合社' => 'FJRCU',
        '福建省农村信用社' => 'FJRCU',
        '湖南省农村信用社联合社' => 'HUNRCU',
        '湖南省农村信用社' => 'HUNRCU',
        '安徽信用社' => 'AHRCU',
        '广东农村信用社' => 'GDRCU',
        '四川省农村信用社' => 'SCRCU',
        '云南农村信用社' => 'YNRCC',
        '云南省农村信用社联合社' => 'YNRCC',
        '贵州省农村信用社' => 'GZRCU',

        // 农商銀行
        '广州农商银行' => 'GZRCBK',
        '广州省农村商业银行' => 'GZRCBK',
        '东莞农商银行' => 'DRCBANK',
        '东莞农商' => 'DRCBANK',
        '深圳农商银行' => 'SRCCCNBS',
        '深圳农村商业银行' => 'SRCCCNBS',
        '顺德农商银行' => 'RCCSCNBS',
        '重庆农村商业银行' => 'CQRCB',

        // 其他銀行
        '东亚银行(中国)' => 'BEASCNSH',
        '东营银行' => 'DYSHCNBJ',
        '包商银行' => 'BTCBCNBJ',
        '台州银行' => 'TZBKCNBT',
        '宁波通商银行' => 'BINHCN2N',
        '宁夏银行' => 'YCCBCNBY',
        '汉口银行' => 'WHCBCNBN',
        '长安银行' => 'CABZCNB1',
        '龙江银行' => 'LJBCCNBH',
        '华融湘江银行' => 'HRXJCNBC',
        '吉林银行' => 'JLBKCNBJ',
        '成都银行' => 'CBOCCNBC',
        '西安银行' => 'IXABCNBX',
        '齐商银行' => 'ZBBKCNBZ',
        '齐鲁银行' => 'JNSHCNBN',
        '昆仑银行' => 'CKLBCNBJ',
        '杭州银行' => 'HZCBCN2H',
        '河北银行' => 'BKSHCNBJ',
        '邯郸银行' => 'BKHDCNB1',
        '金华银行' => 'JHCBCNBJ',
        '阜新银行' => 'FXBKCNBJ',
        '青海银行' => 'BOXNCNBL',
        '南昌银行' => 'NCCKCNBN',
        '哈尔滨银行' => 'HCCBCNBH',
        '泉州银行' => 'BKQZCNBZ',
        '洛阳银行' => 'BOLYCNB1',
        '济宁银行' => 'BKJNCNBJ',
        '晋商银行' => 'JSHBCNBJ',
        '浙江民泰商业银行' => 'ZJMTCNSH',
        '浙江泰隆商业银行' => 'ZJTLCNBH',
        '浙江稠州商业银行' => 'CZCBCN2X',
        '烟台银行' => 'YTCBCNSD',
        '苏州银行' => 'DWRBCNSU',
        '常熟农商银行' => 'CSCBCNSH',
        '厦门国际银行' => 'IBXHCNBA',
        '富邦华一银行' => 'FSBCCNSH',
        '富滇银行' => 'KCCBCN2K',
        '廊坊银行' => 'BOLFCNBL',
        '温州银行' => 'WZCBCNSH',
        '湖北银行' => 'HBBKCNBN',
        '新韩银行(中国)' => 'SHBKCNBJ',
        '嘉兴银行' => 'BOJXCNBJ',
        '福建海峡银行' => 'FZCBCNBS',
        '德州银行' => 'DECLCNBJ',
        '重庆银行' => 'CQCBCN22',
        '江西银行' => 'JXB',
        '贵州银行' => 'GZB',
        '甘肃银行' => 'GSB',
        '内蒙古银行' => 'HSSYCNBH',

        // 支付平台
        '支付宝' => 'ALIPAY',
        '微信' => 'WEIXIN'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "cus_code" => $data["merchant"],
            'cus_order_sn' => $data['order_number'],
            'payment_flag' => $data['key2'] ?: $this->channelCodeMap[$this->channelCode],
            'amount' => $data['request']->amount,
            'notify_url' => $data['callback_url'],
            'end_user_ip' => $data['request']->client_ip ?? $data['client_ip'] ?? '168.168.168.168',
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['attach_data'] = json_encode([
                "card_name" => $data['request']->real_name,
                'user_id' => rand(00000001, 99999999),
            ]);
        }

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

        if ($row['status'] != 200) {
            return [
                'success' => false,
                'msg' => data_get($row, 'message', '')
            ];
        }

        $extraInfo = data_get($row, 'extra_data.card_info', []);

        return [
            'success' => true,
            'data' => [
                'pay_url' => data_get($row, 'order_info.payment_uri', ''),
                'receiver_account' => $extraInfo['bank_account'] ?? $extraInfo['card_number'] ?? '',
                'receiver_bank_name' => $extraInfo['bank_name'] ?? '',
                'receiver_bank_branch' => $extraInfo['bank_branch'] ?? '',
                'receiver_name' => $extraInfo['bank_account_name'] ?? $extraInfo['card_name'] ?? '',
            ]
        ];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $bankCode = $this->bankCodeMap[$data['request']->bank_name];

        if (!$bankCode) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $postBody = [
            "cus_code" => $data["merchant"],
            'cus_order_sn' => $data['order_number'],
            'payment_flag' => 'pay_bundle',
            'amount' => $data['request']->amount,
            'bank_code' => $bankCode,
            'bank_account' => $data['request']->bank_card_number,
            'account_name' => $data['request']->bank_card_holder_name,
            'notify_url' => $data['callback_url'],
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false, "msg" => $e->getMessage() ?? ""];
        }
        return ["success" => false, "msg" => $row["message"] ?? ""];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postBody = [
            "cus_code" => $data["merchant"],
            'cus_order_sn' => $data['order_number'] ?? $data['request']->order_number,
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false, "msg" => $e->getMessage() ?? ""];
        }

        if ($row['status'] != 200) {
            return [
                'success' => false,
            ];
        }

        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        $sign = $this->makesign($data, $thirdChannel->key);
        if ($data["sign"] != $sign) {
            return ["error" => "簽名錯誤"];
        }

        if ($data["cus_order_sn"] != $transaction->order_number && $data['cus_order_sn'] != $transaction->system_order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($data["original_amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], ["success"])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], ["failed"])) {
            return ['fail' => '逾时', "msg" => $data["message"] ?? ""];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "cus_code" => $data["merchant"],
            "ut" => time(),
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "json", false);
            if ($row["status"] == 200) {
                $balance = $row["account_info"]["total_deposit"];
                ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                    "balance" => $balance,
                ]);
                return $balance;
            }
            return 0;
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            Log::error(self::class, compact('data', 'message'));
            return 0;
        }
    }

    private function sendRequest($url, $data, $type = "json", $debug = true)
    {
        try {
            $client = new Client();
            $data["sign"] = $this->makesign($data, $this->key);
            $response = $client->request('POST', $url, [
                "form_params" => $data
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $e;
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = http_build_query($body) . "&key=$key";
        return md5($signStr);
    }
}
