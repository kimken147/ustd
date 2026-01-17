<?php

namespace App\ThirdChannel;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use App\Utils\BCMathUtil;

class WPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'WPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl = 'https://api.wpayba.com/wpay/api/createOrder';
    public $xiafaUrl   = 'https://api.wpayba.com/wpay/api/applyDrawcash';
    public $daifuUrl   = 'https://api.wpayba.com/wpay/api/applyDrawcash';
    public $queryDepositUrl = 'https://api.wpayba.com/wpay/api/order/status';
    public $queryDaifuUrl  = 'https://api.wpayba.com/wpay/api/drawcash/status';
    public $queryBalanceUrl = 'https://api.wpayba.com/wpay/api/merchantBalance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => '6'
    ];

    public $bankMap = [
        '农业银行' => 'ABC',
        '网商银行' => 'ANTBANK',
        '安徽省农村信用社' => 'ARCU',
        '鞍山银行' => 'ASCB',
        '潍坊银行' => 'BANKWF',
        '保定银行' => 'BDCBANK',
        '广西北部湾银行' => 'BGB',
        '河北银行' => 'BHB',
        '北京银行' => 'BJBANK',
        '北京农商行' => 'BJRCB',
        '中国银行' => 'BOC',
        '承德银行' => 'BOCD',
        '中银富登村镇银行' => 'BOCFCB',
        '朝阳银行' => 'BOCY',
        '沧州银行' => 'BOCZ',
        '东莞银行' => 'BOD',
        '渤海银行' => 'BOHAIB',
        '海南省农村信用社' => 'BOHN',
        '锦州银行' => 'BOJZ',
        '洛阳银行' => 'BOL',
        '平顶山银行' => 'BOP',
        '青海银行' => 'BOQH',
        '泉州银行' => 'BOQZ',
        '新韩银行' => 'BOSH',
        '苏州银行' => 'BOSZ',
        '营口银行' => 'BOYK',
        '包商银行' => 'BSB',
        '长安银行' => 'CABANK',
        '建设银行' => 'CCB',
        '长春朝阳和润村镇银行' => 'CCHRCZYH',
        '重庆三峡银行' => 'CCQTGB',
        '成都银行' => 'CDCB',
        '成都农商银行' => 'CDRCB',
        '光大银行' => 'CEB',
        '四川天府银行' => 'CGNB',
        '兴业银行' => 'CIB',
        '中信银行' => 'CITIC',
        '花旗银行' => 'CITICN',
        '江苏长江商业银行' => 'CJCCB',
        '招商银行' => 'CMB',
        '民生银行' => 'CMBC',
        '交通银行' => 'COMM',
        '重庆银行' => 'CQBANK',
        '重庆农村商业银行' => 'CRCBANK',
        '长沙银行' => 'CSCB',
        '常熟农商银行' => 'CSRCB',
        '浙商银行' => 'CZBANK',
        '浙江稠州商业银行' => 'CZCB',
        '长治市商业银行' => 'CZCCB',
        '江南农村商业银行' => 'CZRCB',
        '龙江银行' => 'DAQINGB',
        '星展银行' => 'DBSCN',
        '大连银行' => 'DLB',
        '大连农村商业银行' => 'DLRCB',
        '东莞农村商业银行' => 'DRCBCL',
        '长城华西银行' => 'DYCB',
        '东营银行' => 'DYCCB',
        '东营莱商村镇银行' => 'DYLSCB',
        '德州银行' => 'DZBANK',
        '恒丰银行' => 'EGBANK',
        '富邦华一银行' => 'FBBANK',
        '富滇银行' => 'FDB',
        '福建海峡银行' => 'FJHXBC',
        '福建省农村信用社联合社' => 'FJNX',
        '抚顺银行' => 'FSCB',
        '阜新银行' => 'FXCB',
        '广州银行' => 'GCB',
        '广发银行' => 'GDB',
        '广东省农村信用社联合社' => 'GDRCC',
        '广东华兴银行' => 'GHB',
        '桂林银行' => 'GLBANK',
        '广州农村商业银行' => 'GRCB',
        '甘肃银行' => 'GSBANK',
        '甘肃省农村信用社' => 'GSRCU',
        '广西壮族自治区农村信用社联合社' => 'GXRCU',
        '贵阳银行' => 'GYCB',
        '赣州银行' => 'GZB',
        '贵州省农村信用社联合社' => 'GZRCU',
        '内蒙古银行' => 'H3CB',
        '韩亚银行' => 'HANABANK',
        '湖北银行' => 'HBC',
        '河北省农村信用社' => 'HBRCU',
        '邯郸银行' => 'HDBANK',
        '汉口银行' => 'HKB',
        '东亚银行' => 'HKBEA',
        '葫芦岛银行' => 'HLDB',
        '黑龙江省农村信用社联合社' => 'HLJRCU',
        '海南银行股份有限公司' => 'HNBANK',
        '湖南省农村信用社' => 'HNRCC',
        '河南省农村信用社' => 'HNRCU',
        '哈尔滨银行' => 'HRBANK',
        '华融湘江银行' => 'HRXJB',
        '恒生银行' => 'HSB',
        '徽商银行' => 'HSBANK',
        '汇丰银行' => 'HSBC',
        '衡水市商业银行' => 'HSBK',
        '湖商村镇银行' => 'HSCZB',
        '湖北省农信社' => 'HURCB',
        '华夏银行' => 'HXBANK',
        '杭州银行' => 'HZCB',
        '湖州银行' => 'HZCCB',
        '工商银行' => 'ICBC',
        '金华银行' => 'JHBANK',
        '晋城银行' => 'JINCHB',
        '九江银行' => 'JJBANK',
        '长春经开融丰村镇银行' => 'JKRFCZYH',
        '吉林银行' => 'JLBANK',
        '吉林省农村信用社联合社' => 'JLRCU',
        '济宁银行' => 'JNBANK',
        '江苏江阴农村商业银行' => 'JRCB',
        '晋商银行' => 'JSB',
        '江苏银行' => 'JSBANK',
        '江苏省农村信用社联合社' => 'JSRCU',
        '嘉兴银行' => 'JXBANK',
        '江西省农村信用社' => 'JXRCU',
        '晋中银行' => 'JZBANK',
        '焦作中旅银行' => 'JZCBANK',
        '梅县客家村镇银行' => 'KJCZYH',
        '昆仑银行' => 'KLB',
        '昆山农村商业银行' => 'KSRB',
        '廊坊银行' => 'LANGFB',
        '辽宁省农村信用社' => 'LNRCC',
        '莱商银行' => 'LSBANK',
        '临商银行' => 'LSBC',
        '乐山市商业银行' => 'LSCCB',
        '泸州市商业银行' => 'LUZBANK',
        '辽阳银行' => 'LYCB',
        '长春绿园融泰村镇银行' => 'LYRTCZYH',
        '柳州银行' => 'LZCCB',
        '兰州银行' => 'LZYH',
        '浙江民泰商业银行' => 'MTBANK',
        '绵阳市商业银行' => 'MYBANK',
        '宁波银行' => 'NBBANK',
        '宁波通商银行' => 'NBCBANK',
        '宁波鄞州农商行' => 'NBYZ',
        '江西银行' => 'NCB',
        '南洋商业银行' => 'NCBANK',
        '南海农商银行' => 'NHB',
        '南京银行' => 'NJCB',
        '内蒙古农村信用社联合社' => 'NMGNXS',
        '宁夏银行' => 'NXBANK',
        '宁夏黄河农村商业银行' => 'NXRCU',
        '广东南粤银行' => 'NYBANK',
        '鄂尔多斯银行' => 'ORBANK',
        '中国邮政储蓄银行' => 'PSBC',
        '攀枝花市商业银行' => 'PZHCCB',
        '青岛银行' => 'QDCCB',
        '秦皇岛银行' => 'QHDBANK',
        '青海省农村信用社' => 'QHRC',
        '曲靖市商业银行' => 'QJCCB',
        '齐鲁银行' => 'QLBANK',
        '珠海华润银行' => 'RBOZ',
        '日照银行' => 'RZB',
        '四川省农村信用社联合社' => 'SCRCU',
        '顺德农商银行' => 'SDEB',
        '山东省农村信用社联合社' => 'SDRCU',
        '上海银行' => 'SHBANK',
        '上海农商银行' => 'SHRCB',
        '永丰银行' => 'SINO',
        '盛京银行' => 'SJBANK',
        '遂宁银行' => 'SNCCB',
        '平安银行' => 'SPABANK',
        '浦发银行' => 'SPDB',
        '上饶银行' => 'SRBANK',
        '深圳农村商业银行' => 'SRCB',
        '三湘银行' => 'SXBANK',
        '绍兴银行' => 'SXCB',
        '陕西省农信社' => 'SXRCCU',
        '山西省农村信用社' => 'SXRCU',
        '石嘴山银行' => 'SZSBK',
        '泰安银行' => 'TACCB',
        '天津银行' => 'TCCB',
        '江苏太仓农村商业银行' => 'TCRCB',
        '天津滨海农村商业银行' => 'TJBHB',
        '天津农商银行' => 'TRCB',
        '台州银行' => 'TZCB',
        '海丰农商银行' => 'HFRCB',
        '海口联合农商银行' => 'UBCHN',
        '联合村镇银行' => 'URB',
        '乌鲁木齐银行' => 'URMQCCB',
        '乌海银行' => 'WHBANK',
        '威海市商业银行' => 'WHCCB',
        '武汉农村商业银行' => 'WHRCB',
        '苏州农村商业银行' => 'WJRCB',
        '友利银行' => 'WOORI',
        '无锡农村商业银行' => 'WRCB',
        '温州银行' => 'WZCB',
        '温州民商银行' => 'WZMSBANK',
        '西安银行' => 'XABANK',
        '新华村镇银行' => 'XHCZYH',
        '厦门国际银行' => 'XIB',
        '新疆农村信用社' => 'XJRCU',
        '厦门银行' => 'XMBANK',
        '邢台银行' => 'XTB',
        '西藏银行' => 'XZBANK',
        '宜宾市商业银行' => 'YBCCB',
        '尧都农商银行村镇银行' => 'YDNSCZYH',
        '营口沿海银行' => 'YKYHCCB',
        '云南省农村信用社' => 'YNRCC',
        '烟台银行' => 'YTBANK',
        '云南红塔银行' => 'YXCCB',
        '银座村镇银行' => 'YZBANK',
        '齐商银行' => 'ZBCB',
        '自贡银行' => 'ZGCCB',
        '张家口银行' => 'ZJKCCB',
        '浙江省农村信用社联合社' => 'ZJNX',
        '浙江泰隆商业银行' => 'ZJTLCB',
        '张家港农村商业银行' => 'ZRCBANK',
        '百信银行' => 'ZXBXBANK',
        '中原银行' => 'ZYB',
        '贵州银行' => 'ZYCBANK',
        '郑州银行' => 'ZZBANK',
        '枣庄银行' => 'ZZYH'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $math = new BCMathUtil;
        $post = [
            'mch_id' => $data['merchant'] ?? intval($this->merchant),
            'trade_type' => $this->channelCodeMap[$this->channelCode],
            'order_amount' => $math->mul($data['request']->amount, 100, 0),   // 金額單位是分
            'notify_url' => $data['callback_url'],
            'cp_order_no' => $data['request']->order_number,
            'ip' => $data['request']->client_ip ?? $data['client_ip'],
            'order_uid' => ''
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['payer_name'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makeDepositSign($post);
        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['json' => $post]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if (isset($result['retcode']) && in_array($result['retcode'], [0])) {
            $ret = [
                'pay_url' => $result['data']['pay_url'],
            ];
        } else {
            return ['success' => false];
        }
        return ['success' => true, 'data' => $ret];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        if (!isset($this->bankMap[$data['request']->bank_name])) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $this->key = $data['key'];

        $math = new BCMathUtil;
        $postData = [
            'mch_id' => $data['merchant'] ?? $this->merchant,
            'amount' => $math->mul($data['request']->amount, 100, 0),   // 金額單位是分
            'order_no' => $data['request']->order_number,
            'notify_url' => $data['callback_url'],
            'type' => 0,
            'card' => [
                'name' =>  $this->bankMap[$data['request']->bank_name],
                'user_name' => $data['request']->bank_card_holder_name,
                'card_no' => $data['request']->bank_card_number
            ],
            'ts' => now()->timestamp,
        ];

        $postData['sign'] = $this->makeDaifuSign($postData);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['json' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if (isset($result['retcode']) && in_array($result['retcode'], [0])) {
            return ['success' => true];
        } else {
            return ['success' => false];
        }
        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $postData = [
            'mch_id' => $data['merchant'] ?? $this->merchant,
            'order_no' => $data['request']->order_number,
            'ts' => now()->timestamp
        ];
        $postData['sign'] = $this->makeDaifuQuerySign($postData);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], ['json' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('result'));

        if (isset($result['retcode']) && in_array($result['retcode'], [0])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } else {
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        $math = new BCMathUtil;

        if (isset($data['cp_order_no']) && $data['cp_order_no'] != $transaction->order_number) { // 代收訂單號
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['order_no']) && $data['order_no'] != $transaction->order_number) { // 代付訂單號
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['pay_amount']) && $data['pay_amount'] != $math->mul($transaction->amount, 100, 0)) {
            return ['error' => '代收金额不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $math->mul($transaction->amount, 100, 0)) {
            return ['error' => '代付金额不正确'];
        }

        if (isset($data['status']) && in_array($data['status'], ['1'])) {
            return ['success' => true];
        }

        if (isset($data['status']) && in_array($data['status'], ['2'])) {
            return ['fail' => '驳回'];
        }

        if ($transaction->isWithdraw()) {
            return ['error' => '未知错误'];
        } else {
            return ['success' => true]; // 代收只有成功時才會回調
        }
    }

    public function queryBalance($data)
    {
        return 99999999;
    }

    public function makeDepositSign($data)
    {
        $data = Arr::only($data, ['cp_order_no', 'mch_id', 'notify_url', 'order_amount']);
        ksort($data);

        return md5(urldecode(http_build_query($data) . $this->key));
    }

    public function makeDaifuSign($data)
    {
        $data = Arr::only($data, ['amount', 'mch_id', 'ts', 'order_no']);
        ksort($data);

        return md5(urldecode(http_build_query($data) . $this->key));
    }

    public function makeDaifuQuerySign($data)
    {
        ksort($data);
        return md5(urldecode(http_build_query($data) . $this->key));
    }
}
