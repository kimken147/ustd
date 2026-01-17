<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Utils\BCMathUtil;

class MGOPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'MGOPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://mgopaycn.com/gateway/api/v1/payments';
    public $xiafaUrl   = 'https://mgopaycn.com/api/payfor/trans';
    public $daifuUrl   = 'https://mgopaycn.com/gateway/api/v2/payouts';
    public $queryDepositUrl    = 'https://mgopaycn.com/gateway/api/v1/payouts';
    public $queryDaifuUrl  = 'https://mgopaycn.com/api/deposit/inquire';
    public $queryBalanceUrl = 'https://mgopaycn.com/gateway/api/v1/platforms/balance';

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
        Channel::CODE_BANK_CARD => "SVC0001",
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
        "中国农业" => "ABC",
        "农业银行" => "ABC",
        "交通银行" => "COMM",
        "广发银行" => "GDB",
        "中国光大银行" => "CEB",
        "光大银行" => "CEB",
        "兴业银行" => "CIB",
        "平安银行" => "SPABANK",
        "中国民生银行" => "CMBC",
        "民生银行" => "CMBC",
        "华夏银行" => "HXBANK",
        "中国邮政储蓄银行" => "PSBC",
        "邮政银行" => "PSBC",
        "中国邮政" => "PSBC",
        "宁波银行" => "NBBANK	",
        "北京银行" => "BJBANK",
        "浙商银行" => "CZBANK",
        "广州银行" => "GCB",
        "长沙银行" => "CSCB",
        "广西北部湾银行" => "BGB",
        "桂林银行" => "GLBANK	",
        "东莞银行" => "BOD",
        "杭州银行" => "HZCB",
        "广西农信银行" => "GXRCU",
        // "微信固码" => "",
        "吉林银行" => "JLBANK",
        "南京银行" => "NJCB",
        "恒丰银行" => "EGBANK",
        "深圳农商银行" => "SRCB",
        "深圳农商" => "SRCB",
        "深圳农村商业银行" => "SRCB",
        "中信银行" => "CITIC",
        "武汉农商银行" => "WHRCB",
        "威海银行" => "WHCCB",
        "上海银行" => "SHBANK",
        "哈尔滨银行" => "HRBB",
        "青岛银行" => "QDCCB",
        "齐鲁银行" => "QLBANK",
        // "烟台银行" => "",
        "柳州银行" => "LZCCB",
        "重庆农村商业银行" => "CRCBANK",
        "重庆农商银行" => "CRCBANK",
        "重庆农商" => "CRCBANK",
        // "北京商业银行" => "",
        // "北京商银" => "",
        "甘肃省农村信用社" => "GSRCU",
        "甘肃省农村信用社联合社" => "GSRCU",
        "甘肃省农信" => "GSRCU",
        "甘肃农村信用社联合社" => "GSRCU",
        "甘肃农村信用社" => "GSRCU",
        "甘肃农信" => "GSRCU",
        "湖北银行" => "HBC",
        "东莞农村商业银行" => "DRCBCL",
        "东莞农商银行" => "DRCBCL",
        "东莞农商" => "DRCBCL",
        "湖南省农村信用社联合社" => "HNRCC",
        "湖南省农村信用社" => "HNRCC",
        "湖南省农信" => "HNRCC",
        "湖南农村信用社联合社" => "HNRCC",
        "湖南农村信用社" => "HNRCC",
        "湖南农信" => "HNRCC",
        "泰隆银行" => "ZJTLCB",
        "稠州银行" => "CZCB",
        "自贡银行" => "ZGCCB",
        "民泰商业银行" => "MTBANK",
        "民泰商银" => "MTBANK",
        // "银座村镇银行" => "",
        "营口银行" => "BOYK",
        "兰州银行" => "LZYH",
        "临商银行" => "LSBC",
        "江西银行" => "JXB",
        "江西农村信用社" => "JXRCU",
        "江西省农村信用社" => "JXRCU",
        "江西省农村信用社联合社" => "JXRCU",
        "江西省农信" => "JXRCU",
        "江西农村信用社联合社" => "JXRCU",
        "江西农信" => "JXRCU",
        "江苏省农村信用社联合社" => "JSRCU",
        "江苏省农村信用社" => "JSRCU",
        "江苏省农信" => "JSRCU",
        "江苏农村信用社联合社" => "JSRCU",
        "江苏农村信用社" => "JSRCU",
        "江苏农信" => "JSRCU",
        "华融湘江银行" => "HRXJB",
        "大连银行" => "DLB",
        "重庆三峡银行" => "CCQTGB",
        // "东营银行" => "",
        "广东农信" => "GDRCC",
        "广东农村信用社" => "GDRCC",
        "广东农村信用社联合社" => "GDRCC",
        "广东省农信" => "GDRCC",
        "广东省农村信用社" => "GDRCC",
        "广东省农村信用社联合社" => "GDRCC",
        "广州农商" => "GRCB",
        "广州农村商业银行" => "GRCB",
        "广州农商银行" => "GRCB",
        "蒙商银行" => "MSBANK",
        // "长春融丰" => "",
        "保定银行" => "BDBANK",
        "重庆银行" => "CQBANK",
        // "张家口银行" => "",
        "天津银行" => "TCCB",
        "邯郸银行" => "HDBANK",
        "富滇银行" => " FDB",
        "徽商银行" => "HSBANK",
        "承德银行" => "BOCD",
        // "阳光村镇银行" => "",
        "赣州银行" => "GZB",
        // "乐山商业银行" => "",
        // "广东南粤" => "",
        "珠海华润银行" => "CRB",
        "顺德农商银行" => "SDEB",
        "顺德农商" => "SDEB",
        "成都农村商业银行" => "CDRCB",
        "成都农商银行" => "CDRCB",
        "成都农商" => "CDRCB",
        "上海农村商业银行" => "SHRCB",
        "上海农商银行" => "SHRCB",
        "上海农商" => "SHRCB",
        "盛京银行" => "SJBANK",
        "福建海峡银行" => "FJHXBC",
        "郑州银行" => "ZZBANK",
        "上海浦东发展银行" => "SPDB",
        "浦发银行" => "SPDB",
        "厦门银行" => "XMBANK",
        "浙江省农村信用社联合社" => "ZJNX",
        "浙江省农村信用社" => "ZJNX",
        "浙江省农信" => "ZJNX",
        "浙江农村信用社联合社" => "ZJNX",
        "浙江农村信用社" => "ZJNX",
        "浙江农信" => "ZJNX",
        "南宁江南国民村镇银行" => "ZJNX",
        "江南村镇银行" => "ZJNX",
        "山东省农村信用社联合社" => "SDRCU",
        "山东省农村信用社" => "SDRCU",
        "山东省农信" => "SDRCU",
        "山东农村信用社联合社" => "SDRCU",
        "山东农村信用社" => "SDRCU",
        "山东农信" => "SDRCU",
        "中原银行" => "ZYB",
        "乐山市商业银行" => "LSCCB",
        "河南省农村信用社" => "HNRCU",
        "河南省农村信用社联合社" => "HNRCU",
        "河南省农信" => "HNRCU",
        "河南农村信用社联合社" => "HNRCU",
        "河南农村信用社" => "HNRCU",
        "河南农信" => "HNRCU",
        "四川天府银行" => "TFBANK",
        "广西壮族自治区农村信用社联合社" => "GXRCU",
        "广西壮族自治区农村信用社" => "GXRCU",
        "广西壮族自治区农信" => "GXRCU",
        "广西壮族自治区信用社联合社" => "GXRCU",
        "广西壮族自治区信用社" => "GXRCU",
        "广西农村信用社" => "GXRCU",
        "广西农村信用社联合社" => "GXRCU",
        "广西农村信用社" => "GXRCU",
        "广西农信" => "GXRCU",
        "福建省农村信用社联合社" => "FJNX",
        "福建省农村信用社" => "FJNX",
        "福建省农信" => "FJNX",
        "福建农村信用社联合社" => "FJNX",
        "福建农村信用社" => "FJNX",
        "福建农信" => "FJNX",
        "湖北省农村信用社" => "FJNX",
        "湖北省农信社" => "FJNX",
        "湖北省农信" => "FJNX",
        "湖北农村信用社" => "FJNX",
        "湖北农信社" => "FJNX",
        "湖北农信" => "HURCB",
        // "晋中银行" => "",
        "晋城银行" => "JINCHB",
        // "银座银行" => "",
        "安徽省农村信用社联合社" => "ARCU",
        "安徽省农村信用社" => "ARCU",
        "安徽省农信" => "ARCU",
        "安徽农村信用社联合社" => "ARCU",
        "安徽农村信用社" => "ARCU",
        "安徽农信" => "ARCU",
        "广州农村商业银行" => "GRCB",
        "广州农商" => "GRCB",
        "广州省农村商业银行" => "GRCB",
        "广州省农商银行" => "GRCB",
        "广州省农商" => "GRCB",
        // "河南伊川农商银行" => "",
        "四川省农村信用社" => "SCRCU",
        "四川省农信社" => "SCRCU",
        "四川省农信" => "SCRCU",
        "四川农村信用社" => "SCRCU",
        "四川农信社" => "SCRCU",
        "四川农信" => "SCRCU",
        // "珠海市农村信用社" => "",
        "云南省农村信用社联合社" => "YNRCC",
        "云南省农村信用社" => "YNRCC",
        "云南省农信" => "YNRCC",
        "云南农村信用社联合社" => "YNRCC",
        "云南农村信用社" => "YNRCC",
        "云南农信" => "YNRCC",
        // "珠海农商银行" => "",
        // "中旅银行" => "",
        // "青岛农商银行" => "",
        // "青岛农商" => "",
        // "常熟农商银行" => "",
        // "常熟农商" => "",
        // "汉口银行" => "HKB",
        // "江南农村商业银行" => "",
        // "江南农商" => "",
        "九江银行" => "JJBANK",
        "洛阳银行" => "LYBANK",
        // "深圳前海微众银行" => "",
        "苏州银行" => "BOSZ",
        // "微商银行" => "",
        "温州银行" => "WZCB",
        // "萧山农商银行" => "",
        // "萧山农商" => "",
        "长沙农商银行" => "HNNXS",
        "长沙农商" => "HNNXS",
        //  "紫金农商银行" => "",
        // "紫金农商" => "",
        "渤海银行" => "BOHAIB",
        "龙江银行" => "DAQINGB",
        "黑龙江省农村信用社联合社" => "HLJRCC",
        "黑龙江省农村信用社" => "HLJRCC",
        "黑龙江省农信" => "HLJRCC",
        "黑龙江农村信用社联合社" => "HLJRCC",
        "黑龙江农村信用社" => "HLJRCC",
        "黑龙江农信" => "HLJRCC",
        "丹东银行" => "BODD",
        "大连农商银行" => "DLRCB",
        "大连农商" => "DLRCB",
        "甘肃银行" => "GSBANK",
        "贵阳银行" => "GYCB",
        // "桂林国民银行" => "",
        "长安银行" => "CCAB",
        "西安银行" => "XABANK",
        "包商银行" => "BSB",
        // "本溪银行" => "",
        "达州银行" => "DZCCB",
        "成都农商银行" => "CDRCB",
        "成都农商" => "CDRCB",
        "东亚银行" => "HKBEA",
        "抚顺银行" => "FSCB",
        "贵州省农村信用社联合社" => "GZRCU",
        "贵州省农村信用社" => "GZRCU",
        "贵州省农信" => "GZRCU",
        "贵州农村信用社联合社" => "GZRCU",
        "贵州农村信用社" => "GZRCU",
        "贵州农信" => "GZRCU",
        "吉林农信银行" => "JLRCU",
        "吉林省农村信用社联合社" => "JLRCU",
        "吉林省农村信用社" => "JLRCU",
        "吉林省农信" => "JLRCU",
        "吉林农村信用社联合社" => "JLRCU",
        "吉林农村信用社" => "JLRCU",
        "吉林农信" => "JLRCU",
        "吉林农村信用社联合社" => "JLRCU",
        "吉林农信" => "JLRCU",
        // "江苏农商银行" => "",
        // "江苏农商" => "",
        "金华银行" => "JHBANK",
        "锦州银行" => "BOJZ",
        "江苏银行" => "JSBANK",
        "昆仑银行" => "KLB",
        "昆山农村商业银行" => "KSRB",
        "昆山农商银行" => "KSRB",
        "昆山农商" => "KSRB",
        "青海省农村信用社联合社" => "QHRCCB",
        "青海省农村信用社" => "QHRCCB",
        "青海省农信" => "QHRCCB",
        "青海农村信用社联合社" => "QHRCCB",
        "青海农村信用社" => "QHRCCB",
        "青海农信" => "QHRCCB",
        // "上虞农商银行" => "",
        // "上虞农商" => "",
        "绍兴银行" => "SXCB",
        // "太仓农商银行" => "",
        // "太仓农商" => "",
        // "泰安银行" => "",
        "天津农商银行" => "TRCB",
        "天津农商" => "TRCB",
        "邢台银行" => "XTB",
        "张家港农村商业银行" => "ZRCBANK",
        "张家港农商银行" => "ZRCBANK",
        "张家港农商" => "ZRCBANK",
        "江苏长江商业银行" => "CJCCB",
        "长江商业银行" => "CJCCB",
        "长江商银" => "CJCCB",
        // "绵阳商业银行" => "",
        // "绵阳商银" => "",
        "山西省农村信用社联合社" => "SRCU",
        "山西省农村信用社" => "SRCU",
        "山西省农信" => "SRCU",
        "山西农村信用社联合社" => "SRCU",
        "山西农村信用社" => "SRCU",
        "山西农信" => "SRCU",
        // "阳泉商业银行" => "",
        // "桦甸惠民村镇银行" => "",
        "海南省农村信用社联合社" => "HNRCB",
        "海南省农村信用社" => "HNRCB",
        "海南省农信" => "HNRCB",
        "海南农村信用社联合社" => "HNRCB",
        "海南农村信用社" => "HNRCB",
        "海南农信" => "HNRCB",
        // "黄河农信银行" => "",
        // "黄河农信" => "",
        // "江门农商银行" => "",
        // "江门农商" => "",
        "宁夏银行" => "NXBANK",
        "宁夏黄河农村商业银行" => "NXRCU",
        "宁夏黄河农商" => "NXRCU",
        // "深圳福田银座村镇银行" => "",
        "石嘴山银行" => "SZSBK",
        "天津滨海农村商业银行" => "TJBHB",
        // "天津滨海农商" => "",
        // "枣庄银行" => "",
        "浙江网商银行" => "MYB",
        "浙江网商" => "MYB",
        // "广东农商银行" => "",
        // "广东农商" => "",
        "海南银行" => "HNB",
        // "五常惠民村镇银行" => "",
        "唐山银行" => "TSBANK",
        "北京农村商业银行" => "BJRCB",
        "北京农商银行" => "BJRCB",
        "北京农商" => "BJRCB",
        // "南阳村镇银行" => "",
        "鞍山银行" => "ASCB",
        // "中银富登村镇银行" => "",
        "衡水银行" => "HSBK",
        "平顶山银行" => "BOP",
        "内蒙古省农村信用社联合社" => "NMGNXS",
        "内蒙古省农村信用社" => "NMGNXS",
        "内蒙古省农信" => "NMGNXS",
        "内蒙古农村信用社联合社" => "NMGNXS",
        "内蒙古农村信用社" => "NMGNXS",
        "内蒙古农信" => "NMGNXS",
        "内蒙古银行" => "H3CB",
        // "中惠水恒升村镇银行" => "",
        "朝阳银行" => "BOCY",
        // "江西农商银行" => "",
        // "江西农商" => "",
        // "贵阳农商银行" => "",
        // "贵阳农商" => "",
        "泉州银行" => "QZCCB",
        // "安图农商村镇银行" => "",
        // "融兴村镇银行" => "",
        "宁波通商银行" => "NCBANK",
        // "天津宁河村镇银行" => "",
        // "广州增城长江村镇银行" => "",
        "鄞州银行" => "NBYZ",
        // "海丰农商银行" => "",
        "上饶银行" => "SRBANK",
        // 黄梅农村商业银行" => "",
        // "黄梅农商银行" => "",
        // "黄梅农商" => "",
        // "密山农商银行" => "",
        // "义乌联合村镇银行" => "",
        // "广东普宁汇成村镇银行" => "",
        // "浙江诸暨联合村镇银行" => "",
        // "东阳农商银行" => "",
        // "东阳农村商业银行" => "",
        // "日照沪农商村镇银行" => "",
        "浙江农商银行" => " ",
        "鄂尔多斯银行" => "ORBANK",
        // "永州农村商业银行" => "",
        // "潮州农商银行" => "",
        // "义乌农商银行" => "",
        // "海口联合农商银行" => "",
        // "瑞丰银行" => "",
        // "南海省农村信用社联合社" => "",
        // "南海省农村信用社" => "",
        // "南海省农信" => "",
        // "南海农村信用社联合社" => "",
        // "南海农村信用社" => "",
        // "南海农信" => "",
        "山西银行" => "JZBANK",
        "盘锦银行" => "OFPJ",
        // "青田农商银行" => "",
        "河北省农村信用社联合社" => "HBRCU",
        "河北省农村信用社" => "HBRCU",
        "河北省农信" => "HBRCU",
        "河北农村信用社联合社" => "HBRCU",
        "河北农村信用社" => "HBRCU",
        "河北农信" => "HBRCU",
        // "海峡银行" => "",
        "四川银行" => "SCBANK",
        // "安徽怀远农商行" => "",
        "新疆省农村信用社联合社" => "XJRCCB",
        "新疆省农村信用社" => "XJRCCB",
        "新疆省农信" => "XJRCCB",
        "新疆农村信用社联合社" => "XJRCCB",
        "新疆农村信用社" => "XJRCCB",
        "新疆农信" => "XJRCCB",
        // "长春二道农商村镇银行" => "",
        // "昆山省农信社联合社" => "",
        // "昆山省农信社" => "",
        // "昆山省农信" => "",
        // "昆山农信社联合社" => "",
        // "昆山农信社" => "",
        // "昆山农信" => "",
        // "东莞农村银行" => "",
        // "梁山农商银行" => "",
        "乌鲁木齐商业银行" => "URMQCCB",
        // "宁波鄞州农村合作银行" => "",
        // "垦利乐安村镇银行股份有限公司" => "",
        "德州银行" => "DZBANK",
        "苏州农村商业银行" => "WJRCB",
        "苏州农商银行" => "WJRCB",
        "苏州农商" => "WJRCB",
        "新疆银行" => "XJBANK",
        "西藏银行" => "XZB",
        // "渣打银行" => "",
        // "文昌大众村镇银行" => "",
        "汇丰银行" => "HSBC",
        // "支付宝" => "",
        // "海口联合农村商业银行" => "",
        // "海口联合农商银行" => "",
        // "海口联合农商" => "",
        // "微信" => "",
        "曲靖市商业银行" => "QJCCB",
        // "农业发展银行" => "",
        "云南红塔银行" => "YNHT",
        "廊坊银行" => "LANGFB",
        "广东南粤银行" => "NYNB",
        // "顺德农村商业银行" => "",
        // "顺德农商银行" => "",
        // "顺德农商" => "",
        "沧州银行" => "CZCCB",
        // "东莞银行" => "",
        "广东南海农商银行" => "NHB",
        "南海农村商业银行" => "NHB",
        "南海农商银行" => "NHB",
        "南海农商" => "NHB",
        "广东华兴银行" => "GHBANK",
        // "湛江农村商业银行" => "",
        // "湛江农商银行" => "",
        // "湛江农商" => "",
        "河北银行" => "BHB",
        // "邯郸市商业银行" => "",
        // "厦门国际银行" => "XIB",
        // "黔西花都村镇银行" => "",
        // "福建石狮农村商业银行" => "",
        // "福建石狮农商银行" => "",
        // "福建石狮农商" => "",
        // "泉州农村商业银行" => "",
        // "泉州农商银行" => "",
        // "泉州农商" => "",
        "贵州银行" => "BGZ",
        "杭州联合银行" => "URCB",
        "台州银行" => "TZCB",
        // "浙江三门银座村镇银行" => "",
        "富邦华一银行" => "FUBONCHINA",
        // "萧山农村商业银行" => "",
        // "萧山农商银行" => "",
        // "萧山农商" => "",
        // "缙云联合村镇银行" => "",
        // "长兴联合村镇银行" => "",
        // "福泉富民村镇银行" => "",
        // "汪清和润村镇银行" => "",
        "阜新银行" => "FXCB",
        // "淮安农村商业银行" => "",
        // "淮安农商银行" => "",
        // "淮安农商" => "",
        // "新华村镇银行" => "",
        "湖南三湘银行" => "CSXBANK",
        // "朝阳银行" => "",
        "成都银行" => "CDCB",
        "遂宁银行" => "SNCCB",
        // "长城华西银行" => "",
        "辽阳银行" => "LYBK",
        "日照银行" => "RIZHAO",
        "齐商银行" => "ZBCB",
        "潍坊银行" => "BANKWF",
        "莱商银行" => "LSBANK",
        "辽宁省农村信用社联合社" => "INRCC",
        "辽宁省农村信用社" => "INRCC",
        "辽宁省农信" => "INRCC",
        "辽宁农村信用社联合社" => "INRCC",
        "辽宁农村信用社" => "INRCC",
        "辽宁农信" => "INRCC",
        "济宁银行" => "JNBANK",
        "陕西省农村信用社联合社" => "SXRCCU",
        "陕西省农村信用社" => "SXRCCU",
        "陕西省农信" => "SXRCCU",
        "陕西农村信用社联合社" => "SXRCCU",
        "陕西农村信用社" => "SXRCCU",
        "陕西农信" => "SXRCCU",
        "晋商银行" => "JSB",
        // "长治银行" => "",
        // "山西孝义农村商业银行" => "",
        // "山西孝义农商银行" => "",
        // "山西孝义农商" => "",
        // "孝义汇通村镇银行" => "",
        "东营莱商村镇银行" => "DYLS",
        // "泸州银行" => "",
        // "皖南农村商业银行" => "",
        // "皖南农商银行" => "",
        // "皖南农商" => "",
        // "山东农村商业银行" => "",
        // "山东农商银行" => "",
        // "山东农商" => "",
        // "盘锦市商业银行" => "",
        // "福州农村商业银行" => "",
        // "福州农商银行" => "",
        // "福州农商" => "",
        // "湖州银行" => "",
        "三门峡银行" => "SCCB",
        "阳泉银行" => "YQCCB",
        "威海市商业银行" => "WHCCB",
        "陕西信合银行" => "SXNXS",
        "周口银行" => "BOZK",
        "常熟农村商业银行" => "CSRCB",
        "库尔勒市商业银行" => "KORLABANK",
        "五华惠民村镇银行" => "WHHMBK",
        "皖江农村商业银行" => "WJRCU",
        "芜湖泰寿村镇银行" => "WUHUBANK",
        "无锡农村商业银行" => "WRCB",
        "泰安市商业银行" => "TACCB",
        "辽阳市商业银行" => "LYCB",
        "浙江稠州商业银行" => "CZCB",
        "张家口市商业银行" => "ZJKCCB",
        "嘉兴银行" => "JXBANK",
        "遵义市商业银行" => "ZYCBANK",
        "安阳银行" => "AYCB",
        "东营市商业银行" => "DYCCB",
        "苏江南农村商业银行" => "CZRCB",
        "国家开发银行" => "CDB",
        "自贡市商业银行" => "ZGCCB",
        "江苏太仓农村商业银行" => "TCRCB",
        "德阳商业银行" => "DYCB",
        "开封市商业银行" => "CBKF",
        "尧都农商行" => "YDRCB",
        "玉溪市商业银行" => "YXCCB",
        "信阳银行" => "XYBANK	",
        "韩亚银行" => "HANABANK",
        "武汉农村商业银行" => "WHRCB",
        "湖北银行宜昌分行" => "HBYCBANK",
        "许昌银行" => "XCYH",
        "新乡银行" => "XXBANK",
        "农信银清算中心" => "NHQS",
        "江苏江阴农村商业银行" => "JRCB",
        "湖州市商业银行" => "HZCCB",
        "浙江民泰商业银行" => "MTBANK",
        "青海银行" => "BOQH",
        "湖北银行黄石分行" => "HBHSBANK",
        "乌鲁木齐市商业银行" => "URMQCCB	",
        "中山小榄村镇银行" => "XLBANK",
        "宜宾市商业银行" => "YBCCB",
        "浙江泰隆商业银行" => "ZJTLCB",
        "驻马店银行" => "BZMD",
        "南昌银行" => "NCB",
        "南充市商业银行" => "CGNB",
        "城市商业银行资金清算中心" => "CBBQS",
        "深圳福田银座银行" => "YZB",
        "长春朝阳和润村镇银行" => "CCHRCB",
        "龙井榆银村镇银行" => "LJYY",
        "长春绿园融泰村镇银行" => "RTCB",
        "长春经开融丰村镇银行" => "CCRFCB",
        "图门敦银村镇银行" => "TMDYCZYH",
        "延吉和润村镇银行" => "YJHRVB",
        "广西银海国民村镇银行" => "BEEB",
        "雅安市商业银行" => "YACCB",
        "珠江村镇银行" => "ZJRC",
        "永丰银行" => "CORBANK",
        "上海松江富明村镇银行" => "ZXRC",
        "广西上林国民村镇银行" => "SLBEEP",
        "仁怀蒙银村镇银行" => "RHMY",
        "微众银行" => "WEBANK",
        "郾城发展村镇银行" => "YCDVB",
        "长沙农商银" => "HNNXS",
        "江西赣州银座村镇银行" => "GZYZ",
        "西平中原村镇银行" => "ZYCB",
        "广元市贵商村镇银行" => "GYGSCB",
        "浙江绍兴瑞丰农村商业银行" => "BORF",
        "天津津南村镇银行" => "JNCZ",
        "重庆渝北银座村镇银行" => "YBYZB",
        "新韩银行" => "SHINHAN",
        "偃师融兴村镇银行" => "RXVB",
        "渣打银行中国有限公司" => "SCCNBANK",
        "友利银行" => "WOORI",
        "陕西秦农农村商业银行" => "QINNONG",
        "洮南惠民村镇银行" => "TNHM",
        "哈尔滨农村商业银行" => "HRBRCB",
        "上海闵行上银村镇银行" => "MINHANG",
        "辽沈银行" => "LIAOSHEN",
        "象山国民村镇银行" => "XSBANK",
        "三亚农村商业银行" => "SYNSYH",
        "秦皇岛银行" => "QHD",
        "梅州客家村镇银行" => "KJCZYH",
        "务川中银富登村镇银行" => "HBSZ",
        "乌海银行" => "WUHAICB",
        "长葛轩辕村镇银行" => "CGXYYH",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $math = new BCMathUtil;
        $this->key = $data['key'];
        $postBody = [
            "platform_id" => $data["merchant"],
            'service_id' => $data['key3'] ?? 'SVC0001',
            'payment_cl_id' => $data['order_number'],
            'amount' => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分,
            'notify_url' => $data['callback_url'],
            "request_time" => time(),
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['name'] = $data['request']->real_name;
        }

        $postBody["sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody,
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            return ['success' => false, $e->getMessage()];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if ($row["error_code"] == "0000") {
            $ret = [
                'pay_url'   => $row["data"]['link'],
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ["success" => false, "msg" => $row["error_msg"]];
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
        $math = new BCMathUtil;

        if (!$bankCode) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $postBody = [
            "platform_id" => $data["merchant"],
            "service_id" => "SVC0004",
            'payout_cl_id' => $data['order_number'],
            'amount' => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分,
            'notify_url' => $data['callback_url'],
            'name' => $data['request']->bank_card_holder_name,
            'number' => $data['request']->bank_card_number,
            "bank_code" => $bankCode,
            "request_time" => time()
        ];
        $postBody["sign"] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        Log::debug(self::class, compact('data', 'postBody', 'row'));

        if (isset($row["error_code"]) && $row["error_code"] == "0000") {
            return ["success" => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        $sign = $this->makesign($data, $thirdChannel->key);
        $math = new BCMathUtil;

        if ($sign != $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if (isset($data['payment_cl_id']) && $data['payment_cl_id'] != $transaction->order_number && $data['payment_cl_id'] != $transaction->system_order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['amount']) && $data['amount'] != $math->mul($transaction->amount, 100, 0)) {
            return ['error' => '代付金额不正确'];
        }

        //代收检查状态
        if (isset($data['real_amount']) && isset($data['status']) && in_array($data['status'], [2])) {
            return ['success' => true];
        }

        if (isset($data['real_amount']) && isset($data['status']) && in_array($data['status'], [3, 4])) {
            return ['fail' => '逾时'];
        }

        //代付检查状态
        if (isset($data['status']) && in_array($data['status'], [3])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (isset($data['status']) && in_array($data['status'], [4, 5])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $math = new BCMathUtil;
        $headers = [
            "Authorization" => $this->key2
        ];

        try {
            $client = new Client();
            $response = $client->request('get', $data['queryBalanceUrl'], [
                "headers" => $headers
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => true];
        }

        $row = json_decode($response->getBody(), true);

        if ($row["error_code"] == "0000") {
            $balance = $row["data"]["total_balance"];
            $balance = $math->div($balance, 100, 2);
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
        ksort($data);
        $data = urldecode(http_build_query($data));
        $strSign = "$data&$key";
        return md5($strSign);
    }
}
