<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;

// 盟付
class MPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'MPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://api.vkbcbggu.com/sha256/deposit';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://api.vkbcbggu.com/sha256/withdraw';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://api.vkbcbggu.com/sha256/query-order';
    public $queryBalanceUrl = 'https://api.vkbcbggu.com/sha256/balance';

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
        "招商银行" => "0101",
        "中国工商银行" => "0102",
        "工商银行" => "0102",
        "中国建设银行" => "0103",
        "中国建设" => "0103",
        "建设银行" => "0103",
        "上海浦东发展银行" => "0104",
        "浦东发展银行" => "0104",
        "浦发银行" => "0104",
        "中国农业银行" => "0105",
        "农业银行" => "0105",
        "中国农业" => "0105",
        "民生银行" => "0106",
        "中国民生银行" => "0106",
        "兴业银行" => "0107",
        "光大银行" => "0109",
        "中国光大银行" => "0109",
        "中国银行" => "0110",
        "北京银行" => "0111",
        "东亚银行" => "0112",
        "渤海银行" => "0113",
        "平安银行" => "0114",
        "上海农商银行" => "0116",
        "上海农商银行" => "0116",
        "上海农商" => "0116",
        "中国邮政储蓄银行" => "0117",
        "中国邮政银行" => "0117",
        "中国邮储银行" => "0117",
        "中国邮储" => "0117",
        "邮政银行" => "0117",
        "中信银行" => "0118",
        "宁波银行" => "0119",
        "日照银行" => "0120",
        "河北银行" => "0121",
        "河南农村信用社联合社" => "0122",
        "河南农信社" => "0122",
        "河南农村信用社" => "0122",
        "河南农信" => "0122",
        "河南省农村信用社联合社" => "0122",
        "河南省农信社" => "0122",
        "河南省农村信用社" => "0122",
        "河南省农信" => "0122",
        "华夏银行" => "0123",
        "威海市商业银行" => "0124",
        "威海市商银" => "0124",
        "重庆农村商业银行" => "0125",
        "重庆农商" => "0125",
        "大连银行" => "0126",
        "富滇银行" => "0127",
        "上海银行" => "0128",
        "交通银行" => "0129",
        "平安银行" => "0130",
        "广发银行" => "0131",
        "深圳发展银行" => "0132",
        "杭州银行" => "0133",
        "北京农商银行" => "0134",
        "浙商银行" => "0135",
        "浙江泰隆商业银行" => "0136",
        "南京银行" => "0137",
        "恒丰银行" => "0138",
        "广州银行" => "0139",
        "恒生银行" => "0140",
        "恒丰银行" => "0141",
        "中国邮政" => "0142",
        "中国银联" => "0143",
        "中国信托商业银行" => "0144",
        "大连农商银行" => "0145",
        "城市商业银行" => "0146",
        "网关转账" => "0155",
        "贵阳银行" => "15001",
        "山东省农村商业银行" => "15002",
        "山东省农商" => "15002",
        "青岛银行" => "15003",
        "云南省农村信用社联合社" => "15004",
        "云南省农村信用社" => "15004",
        "云南省农信" => "15004",
        "云南农村信用社联合社" => "15004",
        "云南农村信用社" => "15004",
        "云南农信" => "15004",
        "徽商银行" => "15005",
        "齐鲁银行" => "15006",
        "枣庄银行" => "15007",
        "长沙银行" => "15008",
        "江苏银行" => "15009",
        "福建省农村信用社联合社" => "15010",
        "福建省农村信用社" => "15010",
        "福建省农信" => "15010",
        "福建农村信用社联合社" => "15010",
        "福建农村信用社" => "15010",
        "福建农信" => "15010",
        "临商银行" => "15011",
        "龙江银行" => "15012",
        "湖南省农村信用社联合社" => "15013",
        "湖南省农村信用社" => "15013",
        "湖南省农信" => "15013",
        "湖南农村信用社联合社" => "15013",
        "湖南农村信用社" => "15013",
        "湖南农信" => "15013",
        "四川省农村信用社联合社" => "15014",
        "四川省农村信用社" => "15014",
        "四川省农信" => "15014",
        "四川农村信用社联合社" => "15014",
        "四川农村信用社" => "15014",
        "四川农信" => "15014",
        "哈尔滨银行" => "15015",
        "烟台银行" => "15016",
        "九江银行" => "15017",
        "安徽省农村信用社联合社" => "15018",
        "安徽省农村信用社" => "15018",
        "安徽省农信" => "15018",
        "安徽农村信用社联合社" => "15018",
        "安徽农村信用社" => "15018",
        "安徽农信" => "15018",
        "吉林银行" => "15019",
        "盛京银行" => "15020",
        "锦州银行" => "15021",
        "洛阳银行" => "15022",
        "江西农商银行" => "15023",
        "西藏银行" => "15024",
        "河北省农村信用社联合社" => "15025",
        "河北省农村信用社" => "15025",
        "河北省农信" => "15025",
        "河北农村信用社联合社" => "15025",
        "河北农村信用社" => "15025",
        "河北农信" => "15025",
        "广西省农村信用社联合社" => "15026",
        "广西省农村信用社" => "15026",
        "广西省农信" => "15026",
        "广西农村信用社联合社" => "15026",
        "广西农村信用社" => "15026",
        "广西农信" => "15026",
        "广东连州农商" => "15027",
        "广东" => "15027",
        "广州农村商业银行" => "15028",
        "广州农商" => "15028",
        "昆山农村商业银行" => "15029",
        "昆山农商" => "15029",
        "鄞州银行" => "15030",
        "厦门银行" => "15031",
        "莱商银行" => "15032",
        "深圳农村商业银行" => "15033",
        "深圳农商" => "15033",
        "华融湘江银行" => "15034",
        "贵州省农村信用社联合社" => "15035",
        "贵州省农村信用社" => "15035",
        "贵州省农信" => "15035",
        "贵州农村信用社联合社" => "15035",
        "贵州农村信用社" => "15035",
        "贵州农信" => "15035",
        "浙江省农村信用社联合社" => "15036",
        "浙江省农村信用社" => "15036",
        "浙江省农信" => "15036",
        "浙江农村信用社联合社" => "15036",
        "浙江农村信用社" => "15036",
        "浙江农信" => "15036",
        "阜新银行" => "15037",
        "苏州银行" => "15038",
        "浙江民泰商业银行" => "15039",
        "浙江民泰商银" => "15039",
        "陕西信用合作社联合社" => "15040",
        "陕西信用合作社" => "15040",
        "陕西农村信用合作社" => "15040",
        "黑龙江省农村信用联合社" => "15041",
        "黑龙江省农村信用" => "15041",
        "黑龙江省农信" => "15041",
        "黑龙江农村信用联合社" => "15041",
        "黑龙江农村信用" => "15041",
        "黑龙江农信" => "15041",
        "东莞银行" => "15204",
        "甘肃省农村信用社联合社" => "15211",
        "甘肃省农村信用社" => "15211",
        "甘肃省农信" => "15211",
        "甘肃农村信用社联合社" => "15211",
        "甘肃农村信用社" => "15211",
        "甘肃农信" => "15211",
        "天津银行" => "15051",
        "甘肃银行" => "15042",
        "东莞农村商业银行" => "15043",
        "东莞农商" => "15043",
        "泉州银行" => "15044",
        "江西银行" => "15045",
        "长安银行" => "15046",
        "齐商银行" => "15047",
        "贵州银行" => "15048",
        "山西省农村信用社联合社" => "15049",
        "山西省农村信用社" => "15049",
        "山西省农信" => "15049",
        "山西农村信用社联合社" => "15049",
        "山西农村信用社" => "15049",
        "山西农信" => "15049",
        "绍兴银行" => "15050",
        "鞍山银行" => "15052",
        "济宁银行" => "15053",
        "成都农村商业银行" => "15054",
        "成都农商银行" => "15054",
        "成都农商" => "15054",
        "潍坊银行" => "15055",
        "东台农村商业银行" => "15056",
        "东台农商业" => "15056",
        "中原银行" => "15057",
        "桂林银行" => "15058",
        "湖北省农村信用社联合社" => "15059",
        "湖北省农村信用社" => "15059",
        "湖北省农信" => "15059",
        "湖北农村信用社联合社" => "15059",
        "湖北农村信用社" => "15059",
        "湖北农信" => "15059",
        "晋商银行" => "15060",
        "邢台银行" => "15061",
        "东营银行" => "15062",
        "成都银行" => "15063",
        "泰安银行股份有限公司" => "15064",
        "广西北部湾银行" => "15065",
        "内蒙古农信银行" => "15066",
        "内蒙古农信" => "15066",
        "库车国民村镇银行" => "15067",
        "邯郸银行" => "15068",
        "江南农村商业银行" => "15069",
        "江南农商" => "15069",
        "福建海峡银行" => "15070",
        "自贡银行" => "15071",
        "四川银行" => "15072",
        "重庆银行" => "15073",
        "大连农村商业银行" => "15074",
        "大连农商" => "15074",
        "湖州银行" => "15075",
        "蒙商银行" => "15076",
        "化德包商村镇银行" => "15077",
        "乌鲁木齐银行" => "15078",
        "台州银行" => "15079",
        "德州银行" => "15080",
        "汉口银行" => "15081",
        "湖北银行" => "15082",
        "武汉农村商业银行" => "15083",
        "武汉农商" => "15083",
        "昆仑银行" => "15084",
        "江苏省农村信用社联合社" => "15085",
        "江苏省农村信用社" => "15085",
        "江苏省农信" => "15085",
        "江苏农村信用社联合社" => "15085",
        "江苏农村信用社" => "15085",
        "江苏农信" => "15085",
        "柳州银行" => "15086",
        "哈尔滨农商银行" => "15087",
        "哈尔滨农商" => "15087",
        "张家港银行" => "15088",
        "北京农商银行" => "15089",
        "北京农商" => "15089",
        "交通银行" => "15090",
        "华夏银行" => "15091",
        "北京农村商业银行" => "15201",
        "北京农村商银" => "15201",
        "无锡农村商业银行" => "15202",
        "无锡农村商银" => "15202",
        "赣州银行" => "15205",
        "温州银行" => "15212",
        "上饶银行" => "15213",
        "吉林省农村信用社联合社" => "15214",
        "吉林省农村信用社" => "15214",
        "吉林省农信" => "15214",
        "吉林农村信用社联合社" => "15214",
        "吉林农村信用社" => "15214",
        "吉林农信" => "15214",
        "张家口银行" => "15215",
        "天津银行" => "15216",
        "浙江稠州商业银行" => "15217",
        "浙江稠州商银" => "15217",
        "天津银行" => "15218",
        "天津农商银行" => "15219",
        "天津农银" => "15219",
        "兰州银行" => "15220",

    ];


    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $post = [
            "merchant_code" => $data["merchant"],
            'amount' => $data['request']->amount,
            'bank_code' => "0155",
            'callback_url' => $data['callback_url'],
            "hashed_mem_id" => uniqid(),
            'merchant_order_no' => $data['request']->order_number,
            "platform" => "PC",
            "risk_level" => 1,
            "service_type" => $data["key2"] ?? 22,
        ];
        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $post['merchant_user'] = $data['request']->real_name;
        } else {
            $post['merchant_user'] = "張三";
        }
        $post["sign"] = $this->makesign($post, $this->key);
        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'form_params' => $post
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (isset($row['status']) && in_array($row['status'], [1])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'pay_url'   => $row["transaction_url"]
                // 'receiver_name' => $resData["cardname"],
                // 'receiver_bank_name' => $resData["bankname"],
                // 'receiver_account' => $resData["cardNo"],
                // 'receiver_bank_branch' => $resData["subbankname"],
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
        if (!isset($this->bankMap[$data['request']->bank_name])) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }
        $bankCode = $this->bankMap[$data['request']->bank_name];
        $postBody = [
            'amount' => $data['request']->amount,
            "bank_code" => $bankCode,
            "bank_branch" => "空",
            "bank_city" => $data["request"]->bank_city == "" || is_null($data["request"]->bank_city) ? "空" : $data["request"]->bank_city,
            'callback_url' => $data['callback_url'],
            "card_name" => $data["request"]->bank_card_holder_name,
            "card_num" => $data["request"]->bank_card_number,
            "merchant_order_no" => $data["request"]->order_number,
            "platform" => "PC",
            "risk_level" => 1,
            "service_type" => 100,
            "merchant_code" => $data["merchant"],
            "merchant_user" => $data["request"]->bank_card_holder_name
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

        if (isset($row['status']) && in_array($row['status'], ['1'])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "merchant_order_no" => $data["request"]->order_number,
        ];
        $sign = $this->makesign($postBody, $this->key);
        $postBody["sign"] = $sign;

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'form_params' => $postBody
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'postBody', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        if (isset($row['status']) && in_array($row['status'], [1])) {
            return ['success' => true, 'status' => Transaction::STATUS_SUCCESS];
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

        // if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
        //     return ['error' => '金额不正确'];
        // }
        if ($data['merchant_order_no'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['status']) && in_array($data['status'], [1])) {
            return ['success' => true, "resBody" => [
                "status" => 1,
            ]];
        } else if (isset($data['status']) && in_array($data['status'], [0])) {
            return ['fail' => "error", "resBody" => [
                "status" => 0,
                "error_msg" => "error"
            ]];
        }
        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant_code" => $data["merchant"],
            "merchant_order_no" => "-",
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
        if ($row["status"] == 1) {
            $balance = $row["current_balance"];
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
        $signStr = urldecode(http_build_query($body)) . "&key=$key";
        return hash("sha256", $signStr);
    }
}
