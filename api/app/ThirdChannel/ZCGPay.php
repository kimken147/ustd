<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\ThirdChannel as ThirdChannelModel;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class ZCGPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'ZCGPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://api.65258723.com/v1/b2b/payment-orders/place-order';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://api.65258723.com/v1/b2b/payout-orders/place-order';
    public $queryDepositUrl = 'https://mwifuswzv.com/api/pay/orderquery';
    public $queryDaifuUrl = 'https://api.65258723.com/v1/b2b/payout-orders/query-order';
    public $queryBalanceUrl = 'https://api.65258723.com/v1/b2b/merchant-wallet/balances';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = '0000';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => 1,
    ];

    public $bankMap = [
        "中国建设银行" => "CN0001",
        "中国建设" => "CN0001",
        "建设银行" => "CN0001",
        "中国农业银行" => "CN0002",
        "中国农业" => "CN0002",
        "农业银行" => "CN0002",
        "中国工商银行" => "CN0003",
        "工商银行" => "CN0003",
        "中国银行" => "CN0004",
        "民生银行" => "CN0005",
        "中国民生银行" => "CN0005",
        "招商银行" => "CN0006",
        "兴业银行" => "CN0007",
        "北京银行" => "CN0008",
        "交通银行" => "CN0009",
        "中国光大银行" => "CN0010",
        "光大银行" => "CN0010",
        "平安银行" => "CN0011",
        "中国邮政储蓄银行" => "CN0012",
        "邮政银行" => "CN0012",
        "中国邮政" => "CN0012",
        "中信银行" => "CN0013",
        "华夏银行" => "CN0014",
        "广州银行" => "CN0015",
        "上海浦东发展银行" => "CN0016",
        "浦发银行" => "CN0016",
        "广发银行" => "CN0017",
        "深圳农村商业银行" => "CN0018",
        "深圳农商银行" => "CN0018",
        "深圳农商" => "CN0018",
        "三门峡银行" => "CN0019",
        "广西北部湾银行" => "CN0020",
        "阳泉银行" => "CN0021",
        "上海银行" => "CN0022",
        "吉林银行" => "CN0023",
        "上海农村商业银行" => "CN0024",
        "上海农商银行" => "CN0024",
        "上海农商" => "CN0024",
        "威海市商业银行" => "CN0025",
        "潍坊银行" => "CN0026",
        "周口银行" => "CN0027",
        "常熟农村商业银行" => "CN0028",
        "常熟农商银行" => "CN0028",
        "常熟农商" => "CN0028",
        "库尔勒市商业银行" => "CN0029",
        "顺德农商银行" => "CN0030",
        "湖北农信" => "CN0031",
        "无锡农村商业银行" => "CN0032",
        "无锡农商银行" => "CN0032",
        "无锡农商" => "CN0032",
        "朝阳银行" => "CN0033",
        "浙商银行" => "CN0034",
        "邯郸银行" => "CN0035",
        "泰安市商业银行" => "CN0036",
        "东莞银行" => "CN0037",
        "辽阳市商业银行" => "CN0038",
        "广东省农村信用社联合社" => "CN0039",
        "广东农村信用社联合社" => "CN0039",
        "广东省农村信用社" => "CN0039",
        "广东农村信用社" => "CN0039",
        "广东省农信" => "CN0039",
        "广东农信" => "CN0039",
        "兰州银行" => "CN0040",
        "绍兴银行" => "CN0041",
        "渤海银行" => "CN0042",
        "浙江稠州商业银行" => "CN0043",
        "贵州省农村信用社联合社" => "CN0044",
        "贵州农村信用社联合社" => "CN0044",
        "贵州省农村信用社" => "CN0044",
        "贵州农村信用社" => "CN0044",
        "贵州省农信" => "CN0044",
        "贵州农信" => "CN0044",
        "张家口市商业银行" => "CN0045",
        "锦州银行" => "CN0046",
        "吉林农信" => "CN0047",
        "平顶山银行" => "CN0048",
        "上饶银行" => "CN0049",
        "山东省农村信用社联合社" => "CN0050",
        "山东农村信用社联合社" => "CN0050",
        "山东省农村信用社" => "CN0050",
        "山东农村信用社" => "CN0050",
        "山东省农信" => "CN0050",
        "山东农信" => "CN0050",
        "盛京银行" => "CN0051",
        "汉口银行" => "CN0052",
        "广西农村信用社联合社" => "CN0053",
        "广西农村信用社" => "CN0053",
        "广西农信" => "CN0053",
        "宁夏黄河农村商业银行" => "CN0054",
        "宁夏黄河农商银行" => "CN0054",
        "宁夏黄河农商" => "CN0054",
        "包商银行" => "CN0055",
        "江苏银行" => "CN0056",
        "广东南粤银行" => "CN0057",
        "广州农村商业银行" => "CN0058",
        "广州农商银行" => "CN0058",
        "广州农商" => "CN0058",
        "苏州银行" => "CN0059",
        "杭州银行" => "CN0060",
        "德州银行" => "CN0061",
        "鄂尔多斯银行" => "CN0062",
        "湖北银行" => "CN0063",
        "嘉兴银行" => "CN0064",
        "遵义市商业银行" => "CN0065",
        "丹东银行" => "CN0066",
        "湖南省农村信用社联合社" => "CN0067",
        "湖南农村信用社联合社" => "CN0067",
        "湖南省农村信用社" => "CN0067",
        "湖南农村信用社" => "CN0067",
        "湖南省农信" => "CN0067",
        "湖南农信" => "CN0067",
        "安阳银行" => "CN0068",
        "东营市商业银行" => "CN0069",
        "苏江南农村商业银行" => "CN0070",
        "苏江南农商银行" => "CN0070",
        "苏江南农商" => "CN0070",
        "恒丰银行" => "CN0071",
        "国家开发银行" => "CN0072",
        "衡水银行" => "CN0073",
        "自贡市商业银行" => "CN0074",
        "成都银行" => "CN0075",
        "济宁银行" => "CN0076",
        "江苏太仓农村商业银行" => "CN0077",
        "江苏太仓农商银行" => "CN0077",
        "江苏太仓农商" => "CN0077",
        "南京银行" => "CN0078",
        "郑州银行" => "CN0079",
        "洛阳银行" => "CN0080",
        "德阳商业银行" => "CN0081",
        "齐商银行" => "CN0082",
        "抚顺银行" => "CN0083",
        "四川省农村信用社联合社" => "CN0084",
        "四川农村信用社联合社" => "CN0084",
        "四川省农村信用社" => "CN0084",
        "四川农村信用社" => "CN0084",
        "四川省农信" => "CN0084",
        "四川农信" => "CN0084",
        "河北省农村信用社联合社" => "CN0085",
        "河北农村信用社联合社" => "CN0085",
        "河北省农村信用社" => "CN0085",
        "河北农村信用社" => "CN0085",
        "河北省农信" => "CN0085",
        "河北农信" => "CN0085",
        "乐山市商业银行" => "CN0086",
        "莱商银行" => "CN0087",
        "开封市商业银行" => "CN0088",
        "尧都农商行" => "CN0089",
        "河南省农村信用社联合社" => "CN0090",
        "河南农村信用社联合社" => "CN0090",
        "河南省农村信用社" => "CN0090",
        "河南农村信用社" => "CN0090",
        "河南省农信" => "CN0090",
        "河南农信" => "CN0090",
        "云南省农村信用社联合社" => "CN0091",
        "云南农村信用社联合社" => "CN0091",
        "云南省农村信用社" => "CN0091",
        "云南农村信用社" => "CN0091",
        "云南省农信" => "CN0091",
        "云南农信" => "CN0091",
        "内蒙古银行" => "CN0092",
        "玉溪市商业银行" => "CN0093",
        "富滇银行" => "CN0094",
        "江苏省农村信用社联合社" => "CN0095",
        "江苏农村信用社联合社" => "CN0095",
        "江苏省农村信用社" => "CN0095",
        "江苏农村信用社" => "CN0095",
        "江苏省农信" => "CN0095",
        "江苏农信" => "CN0095",
        "信阳银行" => "CN0096",
        "韩亚银行" => "CN0097",
        "石嘴山银行" => "CN0098",
        "晋城银行" => "CN0099",
        "阜新银行" => "CN0100",
        "武汉农村商业银行" => "CN0101",
        "武汉农商银行" => "CN0101",
        "武汉农商" => "CN0101",
        "武汉农商银行" => "CN0101",
        "湖北银行宜昌分行" => "CN0102",
        "台州银行" => "CN0103",
        "江西省农村信用社联合社" => "CN0104",
        "江西农村信用社联合社" => "CN0104",
        "江西省农村信用社" => "CN0104",
        "江西农村信用社" => "CN0104",
        "江西省农信" => "CN0104",
        "江西农信" => "CN0104",
        "张家港农村商业银行" => "CN0105",
        "张家港农商银行" => "CN0105",
        "张家港农商" => "CN0105",
        "晋商银行" => "CN0106",
        "山西银行" => "CN0107",
        "福建海峡银行" => "CN0108",
        "许昌银行" => "CN0109",
        "宁夏银行" => "CN0110",
        "广东南海农村商业银行" => "CN0111",
        "广东南海农商银行" => "CN0111",
        "广东南海农商" => "CN0111",
        "新乡银行" => "CN0112",
        "徽商银行" => "CN0113",
        "九江银行" => "CN0114",
        "农信银清算中心" => "CN0115",
        "江苏江阴农村商业银行" => "CN0116",
        "江苏江阴农商银行" => "CN0116",
        "江苏江阴农商" => "CN0116",
        "湖州市商业银行" => "CN0117",
        "湖州市商银" => "CN0117",
        "浙江民泰商业银行" => "CN0118",
        "浙江民泰商银" => "CN0118",
        "廊坊银行" => "CN0119",
        "鞍山银行" => "CN0120",
        "陕西省农村信用社联合社" => "CN0121",
        "陕西农村信用社联合社" => "CN0121",
        "陕西省农村信用社" => "CN0121",
        "陕西农村信用社" => "CN0121",
        "陕西省农信" => "CN0121",
        "陕西农信" => "CN0121",
        "重庆三峡银行" => "CN0122",
        "大连银行" => "CN0123",
        "东莞农村商业银行" => "CN0124",
        "东莞农商银行" => "CN0124",
        "东莞农商" => "CN0124",
        "宁波银行" => "CN0125",
        "西安银行" => "CN0126",
        "昆仑银行" => "CN0127",
        "重庆农村商业银行" => "CN0128",
        "重庆农商银行" => "CN0128",
        "重庆农商" => "CN0128",
        "营口银行" => "CN0129",
        "昆山农村商业银行" => "CN0130",
        "昆山农商银行" => "CN0130",
        "昆山农商" => "CN0130",
        "华融湘江银行" => "CN0131",
        "桂林银行" => "CN0132",
        "安徽省农村信用社联合社" => "CN0133",
        "安徽农村信用社联合社" => "CN0133",
        "安徽省农村信用社" => "CN0133",
        "安徽农村信用社" => "CN0133",
        "安徽省农信" => "CN0133",
        "安徽农信" => "CN0133",
        "青海银行" => "CN0134",
        "成都农村商业银行" => "CN0135",
        "成都农商银行" => "CN0135",
        "成都农商" => "CN0135",
        "青岛银行" => "CN0136",
        "东亚银行" => "CN0137",
        "甘肃省农村信用社联合社" => "CN0138",
        "甘肃农村信用社联合社" => "CN0138",
        "甘肃省农村信用社" => "CN0138",
        "甘肃农村信用社" => "CN0138",
        "甘肃省农信" => "CN0138",
        "甘肃农信" => "CN0138",
        "浙江省农村信用社联合社" => "CN0139",
        "浙江农村信用社联合社" => "CN0139",
        "浙江省农村信用社" => "CN0139",
        "浙江农村信用社" => "CN0139",
        "浙江省农信" => "CN0139",
        "浙江农信" => "CN0139",
        "湖北银行黄石分行" => "CN0140",
        "温州银行" => "CN0141",
        "天津农村商业银行" => "CN0142",
        "天津农商银行" => "CN0142",
        "天津农商" => "CN0142",
        "乌鲁木齐市商业银行" => "CN0143",
        "中山小榄村镇银行" => "CN0144",
        "长沙银行" => "CN0145",
        "苏州农村商业银行" => "CN0146",
        "苏州农商银行" => "CN0146",
        "苏州农商" => "CN0146",
        "齐鲁银行" => "CN0147",
        "宜宾市商业银行" => "CN0148",
        "浙江泰隆商业银行" => "CN0149",
        "金华银行" => "CN0150",
        "河北银行" => "CN0151",
        "赣州银行" => "CN0152",
        "驻马店银行" => "CN0153",
        "鄞州银行" => "CN0154",
        "临商银行" => "CN0155",
        "贵阳银行" => "CN0156",
        "重庆银行" => "CN0157",
        "承德银行" => "CN0158",
        "北京农村商业银行" => "CN0159",
        "北京农商银行" => "CN0159",
        "北京农商" => "CN0159",
        "南昌银行" => "CN0160",
        "龙江银行" => "CN0161",
        "天津银行" => "CN0162",
        "南充市商业银行" => "CN0163",
        "城市商业银行资金清算中心" => "CN0164",
        "邢台银行" => "CN0165",
        "厦门银行" => "CN0166",
        "福建省农村信用社联合社" => "CN0167",
        "福建农村信用社联合社" => "CN0167",
        "福建省农村信用社" => "CN0167",
        "福建农村信用社" => "CN0167",
        "福建省农信" => "CN0167",
        "福建农信" => "CN0167",
        "厦门国际银行" => "CN0168",
        "汇丰银行" => "CN0169",
        "长安银行" => "CN0170",
        "深圳福田银座银行" => "CN0171",
        "珠海华润银行" => "CN0172",
        "柳州银行" => "CN0173",
        "浙江网商银行" => "CN0174",
        "哈尔滨银行" => "CN0175",
        "江西银行" => "CN0176",
        "中原银行" => "CN0177",
        "长春朝阳和润村镇银行" => "CN0178",
        "龙井榆银村镇银行" => "CN0179",
        "长春绿园融泰村镇银行" => "CN0180",
        "长春经开融丰村镇银行" => "CN0181",
        "图门敦银村镇银行" => "CN0182",
        "延吉和润村镇银行" => "CN0183",
        "广西银海国民村镇银行" => "CN0184",
        "海南省农村信用社联合社" => "CN0185",
        "海南农村信用社联合社" => "CN0185",
        "海南省农村信用社" => "CN0185",
        "海南农村信用社" => "CN0185",
        "海南省农信" => "CN0185",
        "海南农信" => "CN0185",
        "海南银行" => "CN0186",
        "雅安市商业银行" => "CN0187",
        "泉州银行" => "CN0188",
        "山西省农村信用社联合社" => "CN0189",
        "山西农村信用社联合社" => "CN0189",
        "山西省农村信用社" => "CN0189",
        "山西农村信用社" => "CN0189",
        "山西省农信" => "CN0189",
        "山西农信" => "CN0189",
        "黑龙江省农村信用社联合社" => "CN0190",
        "黑龙江农村信用社联合社" => "CN0190",
        "黑龙江省农村信用社" => "CN0190",
        "黑龙江农村信用社" => "CN0190",
        "黑龙江省农信" => "CN0190",
        "黑龙江农信" => "CN0190",
        "内蒙古农村信用社联合社" => "CN0191",
        "内蒙古农村信用社" => "CN0191",
        "内蒙古农信" => "CN0191",
        "富邦华一银行" => "CN0192",
        "贵州银行" => "CN0193",
        "东营莱商村镇银行" => "CN0194",
        "珠江村镇银行" => "CN0195",
        "永丰银行" => "CN0196",
        "杭州联合银行" => "CN0197",
        "广东华兴银行" => "CN0198",
        "湖南三湘银行" => "CN0199",
        "上海松江富明村镇银行" => "CN0200",
        "广西上林国民村镇银行" => "CN0201",
        "日照银行" => "CN0202",
        "仁怀蒙银村镇银行" => "CN0203",
        "天津滨海农村商业银行" => "CN0204",
        "天津滨海农商银行" => "CN0204",
        "天津滨海农商" => "CN0204",
        "微众银行" => "CN0205",
        "辽宁省农村信用社联合社" => "CN0206",
        "辽宁农村信用社联合社" => "CN0206",
        "辽宁省农村信用社" => "CN0206",
        "辽宁农村信用社" => "CN0206",
        "辽宁省农信" => "CN0206",
        "辽宁农信" => "CN0206",
        "郾城发展村镇银行" => "CN0207",
        "四川银行" => "CN0208",
        "新疆农村信用社联合社" => "CN0209",
        "新疆农村信用社" => "CN0209",
        "新疆农信" => "CN0209",
        "唐山银行" => "CN0210",
        "遂宁银行" => "CN0211",
        "四川天府银行" => "CN0212",
        "沧州银行" => "CN0213",
        "保定银行" => "CN0214",
        "长沙农村商业银行" => "CN0215",
        "长沙农商银行" => "CN0215",
        "长沙农商" => "CN0215",
        "江西赣州银座村镇银行" => "CN0216",
        "西平中原村镇银行" => "CN0217",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "merchantCode" => $data["merchant"],
            'merchantOrderId' => $data['order_number'],
            'amount' => $data['request']->amount,
            'notifyUrl' => $data['callback_url'],
            "channelCode" => $data["key2"],
            //"channelTypeId" => $this->channelCodeMap[$this->channelCode],
            "requestTime" => time(),
        ];

        $postBody['payerName'] = $data['request']->real_name ?? '王小明';

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            $resData = $row['data'];

            $ret = [
                'pay_url' => $resData["upstreamLink"] ?? '',
                "receiver_name" => $resData["cardName"] ?? "",
                'receiver_bank_name' => $resData["cardBank"] ?? "",
                'receiver_account' => $resData["cardAccount"] ?? "",
                'receiver_bank_branch' => $resData["cardBranch"] ?? "",
            ];
            return ['success' => true, 'data' => $ret];
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $bankName = $this->bankMap[$data['request']->bank_name] ?? null;
        if (!$bankName) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }
        $this->key = $data['key'];

        $postBody = [
            "merchantCode" => $data["merchant"],
            'merchantOrderId' => $data['system_order_number'],
            'amount' => $data['request']->amount,
            'notifyUrl' => $data['callback_url'],
            "channelCode" => $data["key2"],
            "payeeName" => $data['request']->bank_card_holder_name,
            "payeeAccount" => $data['request']->bank_card_number,
            "payeeBankId" => $bankName,
            "requestTime" => time(),
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false, "msg" => $e->getMessage()];
        }
        if ($result["code"] == "0000") {
            return ["success" => true];
        }
        return ["success" => false, "msg" => $result["message"] ?? ""];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postBody = [
            "merchantCode" => $data["merchant"],
            'merchantOrderId' => $data['system_order_number'],
        ];

        try {
            $result = $this->sendRequest($data["queryDaifuUrl"], $postBody);
            if ($result["code"] != "0000") {
                return ['success' => false];
            }
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, "msg" => $e->getMessage()];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        $sign = $this->makesign($data, $thirdChannel->key);
        if ($data["sign"] != $sign) {
            return ["error" => "簽名錯誤"];
        }

        if (isset($data["payment_cl_id"])) {
            if ($data["payment_cl_id"] != $transaction->order_number &&
                $data["payment_cl_id"] != $transaction->system_order_number) {
                return ['error' => '支付订单编号不正确'];
            }
        }

        if (isset($data["payout_cl_id"])) {
            if ($data["payout_cl_id"] != $transaction->order_number &&
                $data["payout_cl_id"] != $transaction->system_order_number) {
                return ['error' => '提现订单编号不正确'];
            }
        }

        //代收检查金额
        if ((isset($data["amount"]) && $data["amount"] != $transaction->amount)) {
            return ['error' => '金额不正确'];
        }

        //代收检查状态
        if ((isset($data["real_amount"]) && in_array($data["status"], ["2"]))) {
            return ['success' => true];
        } else if (in_array($data["status"], ["3"])) {
            return ['success' => true];
        }

        if ((isset($data["real_amount"]) && in_array($data["status"], ["3", "4"]))) {
            return ['fail' => "驳回"];
        } else if (in_array($data["status"], ["4", "5"])) {
            return ['fail' => "驳回"];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchantCode" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "json", false);
            $balance = $row["data"]["balance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            return 0;
        }
    }

    private function sendRequest($url, $data, $type = "json", $debug = true)
    {
        try {
            $client = new Client();
            $data["sign"] = $this->makesign($data, $this->key);
            $response = $client->request('POST', $url, [
                "json" => $data
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['code'] != "0000") {
                throw new \Exception($row['message'] ?? '');
            }

            return $row;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($e instanceof RequestException) {
                if ($e->hasResponse()) {
                    $response = json_decode($e->getResponse()->getBody()->getContents(), true);
                    $msg = $response["message"];
                }
            }
            Log::error(self::class, [
                'data' => $data,
                'msg' => $msg,
            ]);
            throw $e;
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = urldecode(http_build_query($body)) . "&$key";
        return (md5($signStr));
    }
}
