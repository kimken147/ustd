<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;

class Dongbang extends ThirdChannel
{
    //Log名称
    public $log_name = 'Dongbang';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://dongbang16888.com/pay';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://dongbang16888.com/transfer/apply';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://dongbang16888.com/transfer/query';
    public $queryBalanceUrl = 'https://dongbang16888.com/merchant/balance';

    //预设商户号
    public $merchant = '';

    //预设密钥
    public $key = '';
    public $key2 = '';
    public $key3 = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "8000"
    ];

    public $bankMap = [
        "中国工商银行" => "ICBC",
        "工商银行" => "ICBC",
        "招商银行" => "CMB",
        "中国建设银行" => "CCB",
        "中国建设" => "CCB",
        "建设银行" => "CCB",
        "中国银行" => "BOC",
        "中国农业银行" => "ABC",
        "农业银行" => "ABC",
        "交通银行" => "BCM",
        "广发银行" => "CGB",
        "中国光大银行" => "CEB",
        "光大银行" => "CEB",
        "兴业银行" => "CIB",
        "平安银行" => "PAB",
        "中国民生银行" => "CMBC",
        "民生银行" => "CMBC",
        "华夏银行" => "HXB",
        "中国邮政储蓄银行" => "PSBC",
        "邮政银行" => "PSBC",
        "中国邮政" => "PSBC",
        "宁波银行" => "NBBANK",
        "北京银行" => "BJBANK",
        "浙商银行" => "CZBANK",
        "广州银行" => "GCB",
        "长沙银行" => "CSCB",
        "广西北部湾银行" => "BGB",
        "桂林银行" => "GLB",
        "东莞银行" => "BOD",
        "杭州银行" => "HZCB",
        "江苏银行" => "JSBC",
        "广西农信银行" => "GXRCU",
        "微信固码" => "WSGM WSGM",
        "吉林银行" => "JLBANK",
        "南京银行" => "NJCB",
        "恒丰银行" => "EGBANK",
        "深圳农商银行" => "SRCB",
        "深圳农村商业银行" => "SRCB",
        "中信银行" => "CNCB",
        "武汉农商银行" => "WHRCB",
        "威海银行" => "WHCCB",
        "上海银行" => "SHBANK",
        "哈尔滨银行" => "HRBANK",
        "青岛银行" => "QDCCB",
        "莱商银行" => "LSBANK",
        "齐鲁银行" => "QLBANK",
        "烟台银行" => "YTBANK",
        "柳州银行" => "LZCCB",
        "重庆农村商业银行" => "CRCBANK",
        "北京商业银行" => "BCCB",
        "甘肃省农村信用社" => "GSNX",
        "湖北银行" => "HBC",
        "东莞农村商业银行" => "DRCB",
        "湖南省农村信用社联合社" => "HUNNX",
        "湖南省农村信用社" => "HUNNX",
        "泰隆银行" => "ZJTLCB",
        "稠州银行" => "CZCB",
        "自贡银行" => "ZGCCB",
        "民泰商业银行" => "MTBANK",
        "银座村镇银行" => "YZBANK",
        "营口银行" => "BOYK",
        "兰州银行" => "LZYH",
        "临商银行" => "LSBC",
        "江西银行" => "NCB",
        "江西农村信用社" => "JXRCU",
        "江苏农村信用社联合社" => "JSRCU",
        "华融湘江银行" => "HRXJB",
        "湖北省农信社" => "HURCB",
        "大连银行" => "DLBANK",
        "沧州银行" => "BOCZ",
        "重庆三峡银行" => "CCQTGB",
        "东营银行" => "DYCCB",
        "广东农信" => "GDRC",
        "广东农村信用社" => "GDRC",
        "广州农商" => "GRCB",
        "蒙商银行" => "PERB",
        "长春融丰" => "CCRFCB",
        "保定银行" => "BOB",
        "重庆银行" => "CQBANK",
        "张家口银行" => "ZJKCCB",
        "天津银行" => "TCCB",
        "邯郸银行" => "HDBANK",
        "富滇银行" => " FDB",
        "徽商银行" => "HSBANK",
        "承德银行" => "CDBAN",
        "浙江农信" => "ZJNX",
        "安徽农信" => "ARCU",
        "阳光村镇银行" => "YGCZYH",
        "福建农信" => "FJNX",
        "阜新银行" => "BOFX",
        "赣州银行" => "GZCCB",
        "乐山商业银行" => "LSCCB",
        "广东南粤" => "GDNY",
        "珠海华润银行" => "CRBANK",
        "顺德农商银行" => "SDNBANK",
        "成都农村商业银行" => "CDRCB",
        "上海农村商业银行" => "SHRCB",
        "上海农商银行" => "SHRCB",
        "上海农商" => "SHRCB",
        "盛京银行" => "SJBANK",
        // <!-- "福建海峡银行" => "xxxxxxx", -->
        "郑州银行" => "ZZBANK",
        "上海浦东发展银行" => "SPDB",
        "浦发银行" => "SPDB",
        "厦门银行" => "XMCCB",
        "浙江省农村信用社" => "ZJNX",
        "南宁江南国民村镇银行" => "JRCCB",
        "江南村镇银行" => "JRCCB",
        "山东省农村信用社联合社" => "SDRCU",
        "山东农村信用社" => "SDRCU",
        "山东农信" => "SDRCU",
        "中原银行" => "ZYBANK",
        "乐山市商业银行" => "LSCCB",
        "河南省农村信用社" => "HNRCU",
        "四川天府银行" => "TFBANK",
        "广西壮族自治区农村信用社联合社" => "GXNX",
        "广西农村信用社" => "GXNX",
        "广西自治区农村信用社" => "GXNX",
        "福建省农村信用社联合社" => "FJNXS",
        "福建省农村信用社" => "FJNXS",
        "湖北省农村信用社" => "HBRCC",
        "晋中银行" => "JZBA",
        "晋城银行" => "JCCB",
        "银座银行" => "FTYZB",
        "安徽信用社" => "ARCU",
        "安徽农信" => "ARCU",
        "广州农商银行" => "JPTX",
        "广州省农村商业银行" => "JPTX",
        "广州农信社" => "JPTX",
        // <!-- "河南伊川农商银行" => "xxxxxxx", -->
        "四川省农村信用社" => "SCRCU",
        "四川农信" => "SCRCU",
        // <!-- "珠海市农村信用社" => "xxxxxxx", -->
        "云南农村信用社" => "YNRCC",
        "云南省农村信用社联合社" => "YNRCC",
        "云南农信" => "YNRCC",
        // <!-- "珠海农商银行" => "xxxxxxx", -->
        // <!-- "中旅银行" => "xxxxxxx", -->
        "青岛农商银行" => "QRCB",
        "青岛农商" => "QRCB",
        "福建农村信用社" => "FJNX",
        "广东省农村信用社" => "GDRCU",
        "广东省农信" => "GDRCU",
        "常熟农商银行" => "CSRCB",
        "常熟农商" => "CSRCB",
        "汉口银行" => "HKB",
        "江南农村商业银行" => "JNRCB",
        "江南农商" => "JNRCB",
        "江苏省农村信用社" => "JSNX",
        "江苏省农信" => "JSNX",
        "九江银行" => "JJCCB",
        "洛阳银行" => "LYBANK",
        "深圳前海微众银行" => "WEBANK",
        "苏州银行" => "BOSZ",
        "台州银行" => "TZCB",
        "微商银行" => "SBANK",
        "温州银行" => "WZCB",
        "萧山农商银行" => "ZJXSBANK",
        "萧山农商" => "ZJXSBANK",
        "长沙农商银行" => "CRCB",
        "长沙农商" => "CRCB",
        "紫金农商银行" => "ZJRCBANK",
        "紫金农商" => "ZJRCBANK",
        "渤海银行" => "CBHB",
        "龙江银行" => "LJBANK",
        "黑龙江农村信用社" => "HLJRCU",
        "黑龙江农信" => "HLJRCU",
        "辽阳银行" => "LYCB",
        "丹东银行" => "BODD",
        "大连农商银行" => "DLRCB",
        "大连农商" => "DLRCB",
        "甘肃银行" => "GSBANK",
        "贵阳银行" => "GYCB",
        "贵州银行" => "ZYCBANK",
        "桂林国民银行" => "GLGM",
        "济宁银行" => "BOJN",
        "廊坊银行" => "LANGFB",
        "长安银行" => "CCAB",
        "西安银行" => "XABANK",
        "包商银行" => "BSB",
        "本溪银行" => "BOBZ",
        "达州银行" => "BODZ",
        "成都农商银行" => "CDRCB",
        "成都农商" => "CDRCB",
        "东亚银行" => "HKBEA",
        "抚顺银行" => "FSCB",
        "贵州省农村信用社联合社" => "GZRCU",
        "贵州省农村信用社" => "GZRCU",
        "贵州省农信" => "GZRCU",
        "河北银行" => "BHB",
        "吉林农信银行" => "JLRCU",
        "吉林农信" => "JLRCU",
        "江苏农商银行" => "JRCB",
        "江苏农商" => "JRCB",
        "金华银行" => "JHBANK",
        "锦州银行" => "BOJZ",
        "江苏银行" => "JSB",
        "昆仑银行" => "KLB",
        "昆山农商银行" => "KSRB",
        "昆山农商" => "KSRB",
        "青海省农村信用社" => "QHRC",
        "青海省农信" => "QHRC",
        "上虞农商银行" => "SYCB",
        "上虞农商" => "SYCB",
        "绍兴银行" => "SXCB",
        "太仓农商银行" => "TCRCB",
        "太仓农商" => "TCRCB",
        "泰安银行" => "TACCB",
        "天津农商银行" => "TRCB",
        "天津农商" => "TRCB",
        "邢台银行" => "XTB",
        "张家港农商银行" => "RCBOZ",
        "张家港农商" => "RCBOZ",
        "长江商业银行" => "JSCCB",
        "绵阳商业银行" => "MYCC",
        "山西农信" => "SRCU",
        "阳泉商业银行" => "YQCCB",
        "桦甸惠民村镇银行" => "HDHMB",
        "海南农信" => "HNB",
        "黄河农信银行" => "HHNX",
        "江门农商银行" => "JMRCB",
        "江门农商" => "JMRCB",
        "宁夏银行" => "NXBANK",
        "宁夏黄河农村商业银行" => "NXRCU",
        "宁夏黄河农商" => "NXRCU",
        "深圳福田银座村镇银行" => "FTYZB",
        "石嘴山银行" => "SZSCCB",
        "苏州农商银行" => "WJRCB",
        "苏州农商" => "WJRCB",
        "天津滨海农村商业银行" => "TJBHB",
        "天津滨海农商" => "TJBHB",
        "枣庄银行" => "ZZB",
        "浙江网商银行" => "MYBANK",
        "浙江网商" => "MYBANK",
        "广东农商银行" => "GZRCB",
        "广东农商" => "GZRCB",
        "海南银行" => "HNBANK",
        "五常惠民村镇银行" => "WCHMB",
        "唐山银行" => "TSB",
        "北京农村商业银行" => "BJRCB",
        "北京农商" => "BJRCB",
        "南阳村镇银行" => "NYCBANK",
        "鞍山银行" => "ASBANK",
        "中银富登村镇银行" => "BOCFTB",
        "衡水银行" => "HSB",
        "平顶山银行" => "PDSB",
        "内蒙古农村信用社" => "NMGNXS",
        "内蒙古农信" => "NMGNXS",
        "内蒙古银行" => "BOIMC",
        "中惠水恒升村镇银行" => "HSHSB",
        "朝阳银行" => "CYCB",
        "江西农商银行" => "JXNXS",
        "江西农商" => "JXNXS",
        "贵阳农商银行" => "GYNSH",
        "贵阳农商" => "GYNSH",
        "泉州银行" => "QZCCB",
        "安图农商村镇银行" => "ATCZB",
        "融兴村镇银行" => "RXVB",
        "宁波通商银行" => "NCBANK",
        "天津宁河村镇银行" => "NINGHEB",
        "广州增城长江村镇银行" => "ZCCJB",
        "鄞州银行" => "BEEB",
        "海丰农商银行" => "HFRCB",
        "上饶银行" => "SRBANK",
        "黄梅农村商业银行" => "HMRCB",
        "陕西省农村信用社联合社" => "SXRCCU",
        "密山农商银行" => "MSRCB",
        "义乌联合村镇银行" => "ZJYURB",
        "广东普宁汇成村镇银行" => "GDPHCB",
        "海南省农村信用社联合社" => "HAINANBANK",
        "浙江诸暨联合村镇银行" => "ZJURB",
        "东阳农商银行" => "ZJDYB",
        "日照沪农商村镇银行" => "SRCBCZ",
        "浙江农商银行" => "ZJRCB ",
        "鄂尔多斯银行" => "ORDOSB",
        "永州农村商业银行" => "HNNXS",
        "潮州农商银行" => "GRCBANK",
        "义乌农商银行" => "YRCBANK",
        "海口联合农商银行" => "UNITEDB",
        "瑞丰银行" => "BORF",
        "南海农信" => "NRCBANK",
        "山西银行" => "SHXIBANK",
        "盘锦银行" => "PJBANK",
        "海南省农村信用社" => "HAINANNONGXIN",
        "青田农商银行" => "QTRCB",
        "河北省农村信用社" => "HEBNX",
        "海南农村信用社" => "HNBB",
        "海峡银行" => "FJHXBANK",
        "四川银行" => "SCB",
        "安徽怀远农商行" => "AHHYRCB",
        "新疆农村信用社" => "XJRCU",
        "长春二道农商村镇银行" => "CCEDCB",
        "山西省农村信用社" => "SXRCU",
        "吉林省农村信用社" => "JLRCUU",
        "昆山农信社" => "KRCB",
        "东莞农村银行" => "DRCBANK",
        "河北农信" => "HBNCXYS",
        "梁山农商银行" => "LSRCB",
        "吉林农村信用社" => "JLNLS",
        "乌鲁木齐商业银行" => "URMQCCB",
        "宁波鄞州农村合作银行" => "NBNCYH",
        "垦利乐安村镇银行股份有限公司" => "kllabank",
        "德州银行" => "DZBCHINA",
        "苏州农村商业银行" => "SZRCB",
        "新疆银行" => "XJBANK",
        "蒙商银行" => "MSBANK",
        "西藏银行" => "XZB",
        "渣打银行" => "SCHK",
        "文昌大众村镇银行" => "WCRCB",
        "汇丰银行" => "HSBC",
        "支付宝" => "ALIPAY",
        "海口联合农商银行" => "HKUNS",
        "微信" => "WECHAT",
        "曲靖市商业银行" => "QJCCB",
        "农业发展银行" => "ADBC",
        "云南红塔银行" => "YNHTBANK",
        "廊坊银行" => "LCCB",
        "广东南粤银行" => "GDNYB",
        "顺德农村商业银行" => "SDEB",
        "顺德农商" => "SDEB",
        "沧州银行" => "BANKCZ",
        "东莞银行" => "DONGGUANB",
        "南海农商银行" => "NHRCB",
        "南海农商" => "NHRCB",
        "广东华兴银行" => "GDHX",
        "邢台银行" => "XTBANK",
        "湛江农村商业银行" => "ZJNRCB",
        "河北银行" => "HEBB",
        "邯郸市商业银行" => "HDCB",
        "厦门国际银行" => "XIB",
        "黔西花都村镇银行" => "QXHDB",
        "福建石狮农商银行" => "FJSSNX",
        "贵阳银行" => "BGY",
        "泉州农村商业银行" => "QZNSX",
        "浙商银行" => "CZB",
        "贵州银行" => "BGZ",
        "浙江农村信用社联合社" => "ZJRC",
        "杭州联合银行" => "HZURCB",
        "重庆农村商业银行" => "CQRCB",
        "台州银行" => "TZBANK",
        "浙江三门银座村镇银行" => "SMYZB",
        "宁波银行" => "NBCB",
        "金华银行" => "JHCCB",
        "绍兴银行" => "SXCCB",
        "哈尔滨银行" => "HRBB",
        "富邦华一银行" => "FUBON",
        "萧山农商银行" => "ZJXSB",
        "缙云联合村镇银行" => "JYCZBANK",
        "长兴联合村镇银行" => "ZJCURB",
        "福泉富民村镇银行" => "FQFMBANK",
        "安徽省农村信用社" => "AHRCU",
        "苏州银行" => "SZBANK",
        "天津银行" => "BTJ",
        "北京农商银行" => "BRCB",
        "汪清和润村镇银行" => "WQHRCZB",
        "阜新银行" => "FXBANK",
        "昆山农商银行" => "KSRCB",
        "淮安农商银行" => "HARCB",
        "新华村镇银行" => "XHBANK",
        "鞍山银行" => "BOAS",
        "湖南三湘银行" => "CSXBANK",
        "上海银行" => "SHB",
        "朝阳银行" => "BOCY",
        "成都银行" => "BOCD",
        "遂宁银行" => "SNCCB",
        "长城华西银行" => "DYCCBB",
        "达州银行" => "DZCCB",
        "辽阳银行" => "BOLY",
        "广西北部湾银行" => "BOBBG",
        "抚顺银行" => "FSBANK",
        "日照银行" => "BORZ",
        "齐商银行" => "QSBANK",
        "潍坊银行" => "BANKWF",
        "莱商银行" => "LAISHANG",
        "辽宁省农村信用社" => "LNRCC",
        "辽宁省农信" => "LNRCC",
        "辽宁农信" => "LNRCC",
        "济宁银行" => "JNBANK",
        "德州银行" => "DZBANK",
        "黑龙江省农村信用社" => "HLJRCC",
        // <!-- "江西银行" => "xxxxxxx", -->
        "中旅银行" => "JXBANK",
        "长安银行" => "CCABANK",
        "陕西农村信用社" => "SXNXS",
        "湖北银行" => "HBB",
        "晋商银行" => "JSHB",
        "长治银行" => "SHXIB",
        "山西孝义农村商业银行" => "SXXYRCU",
        "孝义汇通村镇银行" => "XYHTCB",
        "东营莱商村镇银行" => "DYLSBANK",
        "泸州银行" => "LZBANK",
        "皖南农商银行" => "WNSCB",
        "山东农商银行" => "SDNCB",
        "盘锦市商业银行" => "PJSCB",
        "福州农商银行" => "FZRCB",
        "湖州银行" => "HZCCB",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $post = [
            'amount' => $data['request']->amount,
            'merchant' => $data['merchant'],
            'paytype' => $data['key3'] ?: "wangguan1",
            'outtradeno' => $data['request']->order_number,
            'notifyurl' => $data['callback_url'],
            "returndataformat" => "serverhtml",
        ];
        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['payername'] = $data['request']->real_name;
        } else {
            $post['payername'] = "張三";
        }
        $post["sign"] = $this->makesign($post, $this->key);
        $postHeaders['Content-Type'] = 'application/x-www-form-urlencoded';
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                "headers" => $postHeaders,
                'form_params' => $post
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['code']) && in_array($row['code'], [0])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'   =>  $row["results"],
                // 'receiver_name' => $data["cardname"],
                // 'receiver_bank_name' => $data["bankname"],
                // 'receiver_account' => $data["cardNo"],
                // 'receiver_bank_branch' => $data["subbankname"],
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
        $postBody = [
            'amount' => $data['request']->amount,
            "merchant" => $data["merchant"],
            "bankname" => $data['request']->bank_name,
            "cardno" => $data["request"]->bank_card_number,
            "cardname" => $data["request"]->bank_card_holder_name,
            'notifyurl' => $data['callback_url'],
            "outtransferno" => $data["request"]->order_number
        ];
        $sign = $this->makesign($postBody, $this->key);
        $postBody["sign"] = $sign;

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'form_params' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false];
        }
        $row = json_decode($response->getBody(), true);
        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if (isset($row['code']) && in_array($row['code'], ['0'])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant" => $data["merchant"],
            "outtransferno" => $data["request"]->order_number,
        ];
        $sign = $this->makesign($data, $this->key2);
        $postBody["sign"] = $sign;

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'form_params' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
        $row = json_decode($response->getBody(), true);
        Log::debug(self::class, compact('data', 'postBody', 'response'));

        if (isset($row['code']) && in_array($row['code'], [0])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($data["sign"] != $sign) {
            return ["error" => "簽名錯誤"];
        }

        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }
        if (isset($data['transferamount']) && $data['transferamount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }
        if (isset($data['outtradeno']) && $data["outtradeno"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['outtransferno']) && $data["outtransferno"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['status']) && in_array($data['status'], [1])) {
            return ['success' => true];
        } else if (isset($data['status']) && in_array($data['status'], [4])) {
            return ['fail' => "error"];
        }
        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant" => $data["merchant"],
        ];
        $postBody["sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryBalanceUrl'], [
                'form_params' => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        $row = json_decode($response->getBody(), true);
        if ($row["code"] == 0) {
            $balance = $row["results"]["availableamount"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        foreach ($body as $key1 => $value) {
            if (is_null($value)) {
                $body[$key1] = '';
            }
        }
        $signStr = http_build_query($body) . "&secret=$key";
        return md5(strtolower($signStr));
    }
}
