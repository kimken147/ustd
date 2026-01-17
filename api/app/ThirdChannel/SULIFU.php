<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class SULIFU extends ThirdChannel
{
    //Log名称
    public $log_name   = 'SULIFU';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://bkend.detrapay.com/dipperPay787SEApi/pay/createOrder';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://bkend.detrapay.com/dipperPay787SEApi/payout/createOrder';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = '';
    public $queryBalanceUrl = 'https://bkend.detrapay.com/dipperPay787SEApi/inquiry/getMerBalance';

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
        Channel::CODE_BANK_CARD => "BankToBank",
    ];

    public $bankMap = [
        "51收款宝" => "dp_SKB51",
        "三峽银行" => "dp_CCQTGB",
        "三门峡银行" => "dp_SCCB",
        "上海农商银行" => "dp_SRCB",
        "上海农商" => "dp_SRCB",
        "上海农村商业银行" => "dp_SRCB",
        "上海银行" => "dp_BANKOFSHANGHAI",
        "上饶银行" => "dp_SRBANK",
        "东亚银行（中国）有限公司" => "dp_HKBEA",
        "东莞农村商业银行" => "dp_DRCBANK",
        "东莞银行" => "dp_DONGGUANBANK",
        "东营银行" => "dp_DYCCB",
        "中信銀行" => "dp_CHINACITICBANK",
        "中原银行" => "dp_ZYBANK",
        "中国光大银行" => "dp_ChinaEverbrightBank",
        "光大银行" => "dp_ChinaEverbrightBank",
        "中国农业银行" => "dp_ABCHINA",
        "农业银行" => "dp_ABCHINA",
        "中国工商银行" => "dp_ICBC",
        "工商银行" => "dp_ICBC",
        "中国建设银行" => "dp_CCB",
        "建设银行" => "dp_CCB",
        "中国建设" => "dp_CCB",
        "中国民生银行" => "dp_CMBC",
        "民生银行" => "dp_CMBC",
        "中国邮政储蓄银行" => "dp_PSBC",
        "邮政银行" => "dp_PSBC",
        "中国邮政" => "dp_PSBC",
        "中国银行" => "dp_BANKOFCHINA",
        "中山小榄村镇银行" => "dp_ZHONGSHAN",
        "临商银行" => "dp_LSBCHINA",
        "丹东银行" => "dp_DANDONGBANK",
        "乌鲁木齐市商业银行" => "dp_UCCB",
        "乐山银行" => "dp_LSCCB",
        "九江银行" => "dp_JJCCB",
        "云南省农村信用社联合社" => "dp_YNRCC",
        "云南农村信用社联合社" => "dp_YNRCC",
        "云南省农村信用社" => "dp_YNRCC",
        "云南农村信用社" => "dp_YNRCC",
        "云南省农信" => "dp_YNRCC",
        "云南农信" => "dp_YNRCC",
        "交通银行" => "dp_BANKCOMM",
        "保定银行" => "dp_BDBANK",
        "信阳银行" => "dp_SUNNYBANK",
        "兰州银行" => "dp_LZBANK",
        "兴业银行" => "dp_CIB",
        "内蒙古农村信用社联合社" => "dp_NMGNXS",
        "内蒙古农村信用社" => "dp_NMGNXS",
        "内蒙古农信" => "dp_NMGNXS",
        "内蒙古银行" => "dp_BOIMC",
        "农信银清算中心" => "dp_NONGXINYIN",
        "包商银行股份有限公司" => "dp_BSB",
        "北京农商银行" => "dp_BJRCB",
        "北京农商" => "dp_BJRCB",
        "北京农村商业银行" => "dp_BJRCB",
        "北京银行" => "dp_BEIJING",
        "华夏银行" => "dp_HUAXIABANK",
        "华润银行" => "dp_CRBC",
        "华融湘江银行" => "dp_HRXJBANK",
        "南京银行" => "dp_NJCB",
        "南昌银行" => "dp_NANCHANG",
        "南海农商银行" => "dp_NANHAIBANK",
        "南海农商" => "dp_NANHAIBANK",
        "南海农村商业银行" => "dp_NANHAIBANK",
        "厦门国际银行" => "dp_XIB",
        "厦门银行" => "dp_XMCCB",
        "双阳吉银村镇银行" => "dp_CCSYJYCZYH",
        "台州银行" => "dp_TZBANK",
        "吉林省农村信用社联合社" => "dp_JLNLS",
        "吉林农村信用社联合社" => "dp_JLNLS",
        "吉林省农村信用社" => "dp_JLNLS",
        "吉林农村信用社" => "dp_JLNLS",
        "吉林省农信" => "dp_JLNLS",
        "吉林农信" => "dp_JLNLS",
        "吉林银行" => "dp_JLBANK",
        "吴江农村商业银行" => "dp_WJRCB",
        "周口银行" => "dp_ZHOUKOU",
        "哈密市商业银行" => "dp_HMCCB",
        "哈尔滨银行" => "dp_HRBCB",
        "唐山银行" => "dp_TSBANK",
        "嘉兴银行" => "dp_JXCCB",
        "四川天府銀行" => "dp_TFB",
        "四川天府银行(南充市商业银行)" => "dp_TFYH",
        "四川省农村信用社联合社" => "dp_SCRCU",
        "四川农村信用社联合社" => "dp_SCRCU",
        "四川省农村信用社" => "dp_SCRCU",
        "四川农村信用社" => "dp_SCRCU",
        "四川省农信" => "dp_SCRCU",
        "四川农信" => "dp_SCRCU",
        "四川银行(攀枝花市商业银行)" => "dp_SCBANK",
        "国家开发银行" => "dp_CDB",
        "城市商业银行资金清算中心" => "dp_CCFCCB",
        "大连银行" => "dp_DLB",
        "天津农商银行" => "dp_TRCBANK",
        "天津农商" => "dp_TRCBANK",
        "天津农村商业银行" => "dp_TRCBANK",
        "天津滨海农商银行" => "dp_TJBHB",
        "天津滨海农商" => "dp_TJBHB",
        "天津滨海农村商业银行" => "dp_TJBHB",
        "天津银行" => "dp_BANKOFTIANJIN",
        "威海市商业银行" => "dp_WHCCB",
        "宁夏中宁青银村镇银行" => "dp_NXQY",
        "宁夏银行" => "dp_BANKOFNX",
        "宁波銀行" => "dp_NBCB",
        "安徽省农村信用社联合社" => "dp_AHRCU",
        "安徽农村信用社联合社" => "dp_AHRCU",
        "安徽省农村信用社" => "dp_AHRCU",
        "安徽农村信用社" => "dp_AHRCU",
        "安徽省农信" => "dp_AHRCU",
        "安徽农信" => "dp_AHRCU",
        "安阳银行" => "dp_ANYANG",
        "宜宾市商业银行" => "dp_YBCCB",
        "富滇银行" => "dp_FDB",
        "富邦华一 银行" => "dp_FUBONCHINA",
        "尧都农商行" => "dp_YDNSH",
        "山东省农村信用社联合社" => "dp_SDRCU",
        "山东农村信用社联合社" => "dp_SDRCU",
        "山东省农村信用社" => "dp_SDRCU",
        "山东农村信用社" => "dp_SDRCU",
        "山东省农信" => "dp_SDRCU",
        "山东农信" => "dp_SDRCU",
        "山西省农村信用社联合社" => "dp_SHANXINJ",
        "山西农村信用社联合社" => "dp_SHANXINJ",
        "山西省农村信用社" => "dp_SHANXINJ",
        "山西农村信用社" => "dp_SHANXINJ",
        "山西省农信" => "dp_SHANXINJ",
        "山西农信" => "dp_SHANXINJ",
        "山西银行" => "dp_SHXIBANK",
        "常州农村信用联社" => "dp_CZRCB",
        "常熟农村商业银行" => "dp_CSRCB",
        "常熟农商银行" => "dp_CSRCB",
        "平安银行" => "dp_PINGANBANK",
        "平顶山银行" => "dp_PDSB",
        "广东华兴银行" => "dp_GHBANK",
        "广东南粤银行" => "dp_NYNB",
        "广东省农村信用社联合社" => "dp_GDRC",
        "广东农村信用社联合社" => "dp_GDRC",
        "广东省农村信用社" => "dp_GDRC",
        "广东农村信用社" => "dp_GDRC",
        "广东省农信" => "dp_GDRC",
        "广东农信" => "dp_GDRC",
        "广发银行" => "dp_CGB",
        "广州农商银行" => "dp_GRCBANK",
        "广州农商" => "dp_GRCBANK",
        "广州农村商业银行" => "dp_GRCBANK",
        "广州花都稠州村镇银行" => "dp_CZCBB",
        "广州银行" => "dp_GZCB",
        "广西农村信用社联合社" => "dp_GX966888",
        "广西农村信用社" => "dp_GX966888",
        "广西农信" => "dp_GX966888",
        "广西北部湾银行" => "dp_CORPORBANK",
        "库尔勒市商业银行" => "dp_XJKCCB",
        "廊坊银行" => "dp_LCCB",
        "开封市商业银行" => "dp_CBKF",
        "张家口银行" => "dp_ZJKCCB",
        "张家港农村商业银行" => "dp_ZRCBANK",
        "张家港农商银行" => "dp_ZRCBANK",
        "德州银行" => "dp_DZBANK",
        "德阳商业银行" => "dp_DYCB",
        "徽商银行" => "dp_HSBANK",
        "恒丰银行" => "dp_HFBANK",
        "成都农商银行" => "dp_CDRCB",
        "成都农商" => "dp_CDRCB",
        "成都农村商业银行" => "dp_CDRCB",
        "成都銀行" => "dp_BOCD",
        "承德银行" => "dp_CHENGDEBANK",
        "抚顺银行" => "dp_FUSHUN",
        "招商银行" => "dp_CMBCHINA",
        "支付宝" => "dp_ZFB",
        "新乡银行" => "dp_XINXIANG",
        "新疆农村信用社联合社" => "dp_XJRCCB",
        "新疆农村信用社" => "dp_XJRCCB",
        "新疆农信" => "dp_XJRCCB",
        "无锡农村商业银行" => "dp_WRCB",
        "无锡农商银行" => "dp_WRCB",
        "日照银行" => "dp_BANKOFRIZHAO",
        "昆仑银行" => "dp_KLB",
        "昆山农村商业银行" => "dp_KSRCB",
        "昆山农商银行" => "dp_KSRCB",
        "晋中银行" => "dp_JINZHONG",
        "晋商银行" => "dp_JSHBANK",
        "晋城银行" => "dp_JCCBANK",
        "朝阳银行" => "dp_CYCB",
        "本溪银行" => "dp_BENXI",
        "杭州银行" => "dp_HZCB",
        "柳州银行" => "dp_LZCCB",
        "桂林银行" => "dp_GUILINBANK",
        "桦甸惠民村镇银行" => "dp_HMCZBANK",
        "武汉农村商业银行" => "dp_WHRCBANK",
        "武汉农商银行" => "dp_WHRCBANK",
        "汉口银行" => "dp_HKB",
        "江南农村商业银行" => "dp_JRCB",
        "江南农商银行" => "dp_JRCB",
        "江苏农村商业银行" => "dp_JS96008",
        "江苏农商银行" => "dp_JS96008",
        "江苏省农村信用社联合社" => "dp_JSNX",
        "江苏农村信用社联合社" => "dp_JSNX",
        "江苏省农村信用社" => "dp_JSNX",
        "江苏农村信用社" => "dp_JSNX",
        "江苏省农信" => "dp_JSNX",
        "江苏农信" => "dp_JSNX",
        "江苏银行" => "dp_JSBCHINA",
        "江西省农村信用社联合社" => "dp_JXNXS",
        "江西农村信用社联合社" => "dp_JXNXS",
        "江西省农村信用社" => "dp_JXNXS",
        "江西农村信用社" => "dp_JXNXS",
        "江西省农信" => "dp_JXNXS",
        "江西农信" => "dp_JXNXS",
        "江西银行" => "dp_JXBANK",
        "河北省农村信用社联合社联合社" => "dp_HB96369",
        "河北农村信用社联合社联合社" => "dp_HB96369",
        "河北省农村信用社联合社" => "dp_HB96369",
        "河北农村信用社联合社" => "dp_HB96369",
        "河北省村社联合社" => "dp_HB96369",
        "河北银行" => "dp_HEBBANK",
        "河南省农村信用社联合社" => "dp_HNNX",
        "河南农村信用社联合社" => "dp_HNNX",
        "河南省农村信用社" => "dp_HNNX",
        "河南农村信用社" => "dp_HNNX",
        "河南省农信" => "dp_HNNX",
        "河南农信" => "dp_HNNX",
        "泉州银行" => "dp_QZCCBANK",
        "泰安银行" => "dp_TACCB",
        "洛阳银行" => "dp_LYBANK",
        "济宁银行" => "dp_JNBANK",
        "浙商银行" => "dp_CZBANK",
        "浙江民泰商业银行" => "dp_MINTAIBANK",
        "浙江民泰商银" => "dp_MINTAIBANK",
        "浙江泰隆商业银行" => "dp_ZJTLCB",
        "浙江泰隆商银" => "dp_ZJTLCB",
        "浙江省农村信用社联合社" => "dp_ZJ96596",
        "浙江农村信用社联合社" => "dp_ZJ96596",
        "浙江省农村信用社" => "dp_ZJ96596",
        "浙江农村信用社" => "dp_ZJ96596",
        "浙江省农信" => "dp_ZJ96596",
        "浙江农信" => "dp_ZJ96596",
        "浙江稠州商业银行" => "dp_CZCB",
        "浙江稠州商银" => "dp_CZCB",
        "浙江网商银行" => "dp_MYBANK",
        "浦东发展银行" => "dp_SPDBANK",
        "海南省农村信用社联合社" => "dp_HAINANBANK",
        "海南农村信用社联合社" => "dp_HAINANBANK",
        "海南省农村信用社" => "dp_HAINANBANK",
        "海南农村信用社" => "dp_HAINANBANK",
        "海南省农信" => "dp_HAINANBANK",
        "海南农信" => "dp_HAINANBANK",
        "海口联合农商银行" => "dp_UNITEDBANK",
        "海口联合农商" => "dp_UNITEDBANK",
        "海口联合农村商业银行" => "dp_UNITEDBANK",
        "深圳农村商业银行" => "dp_4001961200",
        "深圳农村商银" => "dp_4001961200",
        "渤海银行" => "dp_CBHB",
        "温州银行" => "dp_WZCB",
        "湖北省农村信用社联合社" => "dp_HURCB",
        "湖北农村信用社联合社" => "dp_HURCB",
        "湖北省农村信用社" => "dp_HURCB",
        "湖北农村信用社" => "dp_HURCB",
        "湖北省农信" => "dp_HURCB",
        "湖北农信" => "dp_HURCB",
        "湖北银行" => "dp_HUBEIBANK",
        "湖南省农村信用社联合社" => "dp_HNNXS",
        "湖南农村信用社联合社" => "dp_HNNXS",
        "湖南省农村信用社" => "dp_HNNXS",
        "湖南农村信用社" => "dp_HNNXS",
        "湖南省农信" => "dp_HNNXS",
        "湖南农信" => "dp_HNNXS",
        "湖州银行" => "dp_HZCCB",
        "潍坊银行" => "dp_BANKWF",
        "烟台银行" => "dp_YANTAIBANK",
        "玉溪市商业银行" => "dp_YXCCB",
        "玉溪市商银" => "dp_YXCCB",
        "瑞丰银行" => "dp_BORF",
        "甘肃省农村信用社联合社" => "dp_GSRCU",
        "甘肃农村信用社联合社" => "dp_GSRCU",
        "甘肃省农村信用社" => "dp_GSRCU",
        "甘肃农村信用社" => "dp_GSRCU",
        "甘肃省农信" => "dp_GSRCU",
        "甘肃农信" => "dp_GSRCU",
        "甘肃银行" => "dp_GSBANKCHINA",
        "盛京银行" => "dp_SHENGJINGBANK",
        "石嘴山银行" => "dp_SZSCCB",
        "福建海峡银行" => "dp_FJHXBANK",
        "福建省农村信用社联合社" => "dp_FJNX",
        "福建农村信用社联合社" => "dp_FJNX",
        "福建省农村信用社" => "dp_FJNX",
        "福建农村信用社" => "dp_FJNX",
        "福建省农信" => "dp_FJNX",
        "福建农信" => "dp_FJNX",
        "绍兴银行" => "dp_SXCCB",
        "绵阳市商业银行" => "dp_MYCCBANK",
        "绵阳市商银" => "dp_MYCCBANK",
        "自贡市商业银行" => "dp_BANKOFZIGONG",
        "自贡市商银" => "dp_BANKOFZIGONG",
        "苏州银行" => "dp_BSZ",
        "莱商银行" => "dp_LSBANKCHINA",
        "营口银行" => "dp_BANKOFYK",
        "衡水银行" => "dp_HENGSHUI",
        "西安银行" => "dp_XACBANK",
        "许昌银行" => "dp_XUCHANG",
        "贵州省农村信用社联合社" => "dp_GZNXBANK",
        "贵州农村信用社联合社" => "dp_GZNXBANK",
        "贵州省农村信用社" => "dp_GZNXBANK",
        "贵州农村信用社" => "dp_GZNXBANK",
        "贵州省农信" => "dp_GZNXBANK",
        "贵州农信" => "dp_GZNXBANK",
        "贵州银行" => "dp_BGZCHINA",
        "贵阳农商银行" => "dp_GYWB",
        "贵阳农商" => "dp_GYWB",
        "贵阳农村商业银行" => "dp_GYWB",
        "贵阳银行" => "dp_BANKGY",
        "赣州银行" => "dp_GZCCB",
        "辽宁省农村信用社联合社" => "dp_LNRCC",
        "辽宁农村信用社联合社" => "dp_LNRCC",
        "辽宁省农村信用社" => "dp_LNRCC",
        "辽宁农村信用社" => "dp_LNRCC",
        "辽宁省农信" => "dp_LNRCC",
        "辽宁农信" => "dp_LNRCC",
        "辽阳市商业银行" => "dp_LYCB",
        "辽阳市商银" => "dp_LYCB",
        "遵义市商业银行" => "dp_ZUNYI",
        "遵义市商银" => "dp_ZUNYI",
        "邢台银行" => "dp_XTBANK",
        "邯郸银行" => "dp_HDCB",
        "郑州银行" => "dp_ZZBANK",
        "鄂尔多斯银行" => "dp_ORBANK",
        "鄞州银行" => "dp_BEEBANK",
        "重庆三峡银行" => "dp_CGTGB",
        "重庆农村商业银行" => "dp_CQRCB",
        "重庆农商银行" => "dp_CQRCB",
        "重庆农商" => "dp_CQRCB",
        "重庆银行" => "dp_CQCBANK",
        "金华银行" => "dp_JHCCB",
        "锦州银行" => "dp_JINZHOUBANK",
        "长安银行" => "dp_CCABCHINA",
        "长沙银行" => "dp_CSYH",
        "阜新银行结算中心" => "dp_FXCB",
        "阳光村镇银行" => "dp_ygczyh",
        "阳泉银行" => "dp_YQCCB",
        "陕西信合" => "dp_SXNXS",
        "陕西省农村信用社联合社联合社" => "dp_SXYXWH",
        "陕西农村信用社联合社联合社" => "dp_SXYXWH",
        "陕西省农村信用社联合社" => "dp_SXYXWH",
        "陕西农村信用社联合社" => "dp_SXYXWH",
        "陕西省村社联合社" => "dp_SXYXWH",
        "青岛银行" => "dp_QDCCB",
        "青海银行" => "dp_BANKQH",
        "鞍山市商业银行" => "dp_BANKOFAS",
        "鞍山市商银" => "dp_BANKOFAS",
        "韩亚银行" => "dp_HANABANK",
        "顺德农商银行" => "dp_SDEBANK",
        "顺德农商" => "dp_SDEBANK",
        "顺德农村商业银行" => "dp_SDEBANK",
        "驻马店银行股份有限公司" => "dp_ZHUMADIAN",
        "黄河农村商业银行" => "dp_BANKYELLOWRIVER",
        "黄河农商银行" => "dp_BANKYELLOWRIVER",
        "黄河农商" => "dp_BANKYELLOWRIVER",
        "黑龙江省农村信用社联合社" => "dp_HLJRCC",
        "黑龙江农村信用社联合社" => "dp_HLJRCC",
        "黑龙江省农村信用社" => "dp_HLJRCC",
        "黑龙江农村信用社" => "dp_HLJRCC",
        "黑龙江省农信" => "dp_HLJRCC",
        "黑龙江农信" => "dp_HLJRCC",
        "齐商银行" => "dp_QSBANK",
        "齐鲁银行" => "dp_QLBCHINA",
        "龙江银行" => "dp_LJBANK",
    ];


    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "merNo" => $data["merchant"],
            'tradeNo' => $data['request']->order_number,
            'cType' => $this->channelCodeMap[$this->channelCode],
            'orderAmount' => $data['request']->amount,
            'notifyUrl' => $data['callback_url'],
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['playerName'] =  $data['request']->real_name;
        }

        $postBody["sign"] = md5($postBody["merNo"] . $postBody["tradeNo"] . $postBody["orderAmount"] . $this->key);

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false];
        }

        if ($row["Success"] == 1) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row["PayPage"] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        }

        return ["success" => false];
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

        $province = "1";
        $city = "1";
        if ($data['request']->bank_province) {
            $province = $data['request']->bank_province;
        }
        if ($data['request']->bank_city) {
            $city = $data['request']->bank_city;
        }

        $postBody = [
            "merNo" => $data["merchant"],
            'tradeNo' => $data['request']->order_number,
            'cType' => "Payout",
            'bankCode' => $bankCode,
            'bankCardNo' => $data['request']->bank_card_number,
            'orderAmount' => $data['request']->amount,
            'accountName' => $data['request']->bank_card_holder_name,
            "openProvince" => $province,
            "openCity" => $city,
            'notifyUrl' => $data['callback_url'],
        ];

        $postBody["sign"] = md5($postBody["merNo"] . $postBody["tradeNo"] . $postBody["bankCode"] . $postBody["orderAmount"] . $this->key);

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }

        if ($result["Success"] == 1) {
            return ["success" => true];
        }
        return ["success" => false];
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $isDeposit = in_array($transaction->type, [Transaction::TYPE_PAUFEN_TRANSACTION, Transaction::TYPE_NORMAL_DEPOSIT]);
        $sign = "";
        if ($isDeposit) {
            $sign = md5($data["tradeNo"] . $data["topupAmount"] . $thirdChannel->key);
        } else {
            $sign = md5($data["tradeNo"] . $data["orderAmount"] . $thirdChannel->key);
        }

        if ($data["sign"] != $sign) {
            return ["error" => "簽名錯誤"];
        }

        if ($data["tradeNo"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($isDeposit) {
            if ($data["topupAmount"] != $transaction->amount) {
                return ['error' => '代收金额不正确'];
            }
        } else {
            if ($data["orderAmount"] != $transaction->amount) {
                return ['error' => '代付金额不正确'];
            }
        }

        //代收检查状态
        if (in_array($data["tradeStatus"], ["1"])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["tradeStatus"], ["-1", "-2"])) {
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

        $postBody["sign"] = md5($postBody["merNo"] . $postBody["datetime"] . $this->key);

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, false);
            if ($row["Success"] == 1) {
                $balance = $row["Balance"];
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

    private function sendRequest($url, $data, $debug = true)
    {
        try {
            $client = new Client();
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
