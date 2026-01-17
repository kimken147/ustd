<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;
use function Psy\debug;

class JieFU extends ThirdChannel
{
    //Log名称
    public $log_name = 'JieFU';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = '';
    public $depositUrl = 'http://gg.ydzf.xyz/api/pay/create_order';
    public $xiafaUrl = '';
    public $daifuUrl = 'http://gg.ydzf.xyz/api/trans/create_order';
    public $queryDepositUrl = 'https://query.zhangcheng888.com/api/pay/query_order';
    public $queryDaifuUrl = 'https://query.zhangcheng888.com/api/trans/query_order';
    public $queryBalanceUrl = 'http://gg.ydzf.xyz/api/account/mch_balance';

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
        Channel::CODE_BANK_CARD => "8007"
    ];

    public $bankMap = [
        "中国工商银行" => "HPT00002",
        "工商银行" => "HPT00002",
        "中国建设银行" => "HPT00003",
        "中国建设" => "HPT00003",
        "建设银行" => "HPT00003",
        "中国农业银行" => "HPT00004",
        "农业银行" => "HPT00004",
        "中国邮政储蓄银行" => "HPT00115",
        "邮政银行" => "HPT00115",
        "中国邮政" => "HPT00115",
        "中国光大银行" => "HPT00022",
        "光大银行" => "HPT00022",
        "招商银行" => "HPT00005",
        "交通银行" => "HPT00006",
        "中信银行" => "HPT00007",
        "兴业银行" => "HPT00008",
        "中国银行" => "HPT00001",
        "中国民生银行" => "HPT00009",
        "民生银行" => "HPT00009",
        "华夏银行" => "HPT00010",
        "广发银行" => "HPT00023",
        "平安银行" => "HPT00024",
        "北京银行" => "HPT00025",
        "上海银行" => "HPT00027",
        "南京银行" => "HPT00028",
        "渤海银行" => "HPT00029",
        "宁波银行" => "HPT00030",
        "上海农村商业银行" => "HPT00033",
        "浙商银行" => "HPT00037",
        "徽商银行" => "HPT00039",
        "广州银行" => "HPT00041",
        "长沙银行" => "HPT00043",
        "青岛银行" => "HPT00044",
        "天津银行" => "HPT00056",
        "恒丰银行" => "HPT00063",
        "成都农村商业银行" => "HPT00064",
        "浙江民泰商业银行" => "HPT00071",
        "泰隆银行" => "HPT00072",
        "福建海峡银行" => "HPT00079",
        "盛京银行" => "HPT00077",
        "莱商银行" => "HPT00082",
        "郑州银行" => "HPT00088",
        "上海浦东发展银行" => "HPT00098",
        "浦发银行" => "HPT00098",
        "厦门银行" => "HPT00099",
        "桂林银行" => "HPT00100",
        "广西北部湾银行" => "HPT00101",
        "浙江省农村信用社联合社" => "HPT00102",
        "浙江省农村信用社" => "HPT00102",
        "浙江省农信" => "HPT00102",
        "浙江农村信用社联合社" => "HPT00102",
        "浙江农村信用社" => "HPT00102",
        "浙江农信" => "HPT00102",
        "南宁江南国民村镇银行" => "HPT00103",
        "重庆农村商业银行" => "HPT00104",
        "重庆农商" => "HPT00104",
        "山东省农村信用社联合社" => "HPT00105",
        "山东省农村信用社" => "HPT00105",
        "山东省农信" => "HPT00105",
        "山东农村信用社联合社" => "HPT00105",
        "山东农村信用社" => "HPT00105",
        "山东农信" => "HPT00105",
        "柳州银行" => "HPT00106",
        "中原银行" => "HPT00107",
        "乐山市商业银行" => "HPT00108",
        "乐山市商银" => "HPT00108",
        "河南省农村信用社联合社" => "HPT00109",
        "河南省农村信用社" => "HPT00109",
        "河南省农信" => "HPT00109",
        "河南农村信用社联合社" => "HPT00109",
        "河南农村信用社" => "HPT00109",
        "河南农信" => "HPT00109",
        "四川天府银行" => "HPT00110",
        "广西壮族自治区农村信用社联合社" => "HPT00111",
        "广西壮族自治区农村信用社" => "HPT00111",
        "广西壮族自治区农信" => "HPT00111",
        "广西自治区农村信用社联合社" => "HPT00111",
        "广西自治区农村信用社" => "HPT00111",
        "广西自治区农信" => "HPT00111",
        "广西农村信用社" => "HPT00111",
        "广西农信" => "HPT00111",
        "福建省农村信用社联合社" => "HPT00112",
        "福建省农村信用社" => "HPT00112",
        "福建省农信" => "HPT00112",
        "福建农村信用社联合社" => "HPT00112",
        "福建农村信用社" => "HPT00112",
        "福建农信" => "HPT00112",
        "湖南省农村信用社联合社" => "HPT00113",
        "湖南省农村信用社" => "HPT00113",
        "湖南省农信" => "HPT00113",
        "湖南农村信用社联合社" => "HPT00113",
        "湖南农村信用社" => "HPT00113",
        "湖南农信" => "HPT00113",
        "湖北省农村信用社联合社" => "HPT00114",
        "湖北省农村信用社" => "HPT00114",
        "湖北省农信" => "HPT00114",
        "湖北农村信用社联合社" => "HPT00114",
        "湖北农村信用社" => "HPT00114",
        "湖北农信" => "HPT00114",
        "张家口银行" => "HPT00116",
        "晋中银行" => "HPT00117",
        "晋城银行" => "HPT00118",
        "银座银行" => "HPT00119",
        "安徽省农村信用社联合社" => "HPT00120",
        "安徽省农村信用社" => "HPT00120",
        "安徽省农信" => "HPT00120",
        "安徽农村信用社联合社" => "HPT00120",
        "安徽农村信用社" => "HPT00120",
        "安徽农信" => "HPT00120",
        "安徽信用社联合社" => "HPT00120",
        "安徽信用社" => "HPT00120",
        "广州省农村商业银行" => "HPT00121",
        "广州农村商业银行" => "HPT00121",
        "广州农商银行" => "HPT00121",
        "广州农商" => "HPT00121",
        "广州商银" => "HPT00121",
        "东莞农商银行" => "HPT00122",
        "东莞农商" => "HPT00122",
        "深圳农村商业银行" => "HPT00123",
        "深圳农商银行" => "HPT00123",
        "深圳农商" => "HPT00123",
        "顺德农商商业银行" => "HPT00124",
        "顺德农商银行" => "HPT00124",
        "顺德农商" => "HPT00124",
        "河南伊川农商银行" => "HPT00125",
        "河南伊川农商" => "HPT00125",
        "广东省农村信用社联合社" => "HPT00128",
        "广东省农村信用社" => "HPT00128",
        "广东省农信" => "HPT00128",
        "广东农村信用社联合社" => "HPT00128",
        "广东农村信用社" => "HPT00128",
        "广东农信" => "HPT00128",
        "四川省农村信用社联合社" => "HPT00130",
        "四川省农村信用社" => "HPT00130",
        "四川省农信" => "HPT00130",
        "四川农村信用社联合社" => "HPT00130",
        "四川农村信用社" => "HPT00130",
        "四川农信" => "HPT00130",
        "江西省农村信用社联合社" => "HPT00131",
        "江西省农村信用社" => "HPT00131",
        "江西省农信" => "HPT00131",
        "江西农村信用社联合社" => "HPT00131",
        "江西农村信用社" => "HPT00131",
        "江西农信" => "HPT00131",
        "珠海市农村信用社联合社" => "HPT00132",
        "珠海市农村信用社" => "HPT00132",
        "珠海市农信" => "HPT00132",
        "珠海农村信用社联合社" => "HPT00132",
        "珠海农村信用社" => "HPT00132",
        "珠海农信" => "HPT00132",
        "云南省农村信用社联合社" => "HPT00133",
        "云南省农村信用社" => "HPT00133",
        "云南省农信" => "HPT00133",
        "云南农村信用社联合社" => "HPT00133",
        "云南农村信用社" => "HPT00133",
        "云南农信" => "HPT00133",
        "重庆银行" => "HPT00134",
        "贵州省农村信用社联合社" => "HPT00135",
        "贵州省农村信用社" => "HPT00135",
        "贵州省农信" => "HPT00135",
        "贵州农村信用社联合社" => "HPT00135",
        "贵州农村信用社" => "HPT00135",
        "贵州农信" => "HPT00135",
        "珠海农商银行" => "HPT00136",
        "珠海农商" => "HPT00136",
        "广东南粤银行" => "HPT00136",
        "中旅银行" => "HPT00138",
    ];


    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $postBody = [
            "mchId" => $data["merchant"],
            'productId' => $data['key2'] ?? $this->channelCodeMap[$this->channelCode],
            "mchOrderNo" => $data["request"]->order_number,
            'amount' => floatval($this->bcMathUtil->mul($data['request']->amount, 100, 2)),
            'notifyUrl' => $data['callback_url'],
            "subject" => $this->channelCodeMap[$this->channelCode],
            "body" => $this->channelCodeMap[$this->channelCode],
            "extra" => $data["request"]->real_name ?? "123",
            'param2' => $data["request"]->real_name ?? "123",
        ];

        try {
            $response = $this->sendRequest($data['url'], $postBody, 'GET');
            return [
                'success' => true,
                'data' => [
                    'pay_url' => $response['payParams']['payUrl'],
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        return ['success' => false, 'msg' => '不支援代付'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if (isset($data['amount']) && $data['amount'] != $this->bcMathUtil->mul($transaction->amount, 100, 2)) {
            return ['error' => '金额不正确'];
        }
        //代收檢查狀態
        if ($data['mchOrderNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }
        if (isset($data['status']) && in_array($data['status'], [2, 3])) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $body = [
            'mchId' => $data['merchant'],
        ];

        try {
            $response = $this->sendRequest($data['queryBalanceUrl'], $body, 'GET', debug: false);
            $balance = $this->bcMathUtil->div($response['balance'], 100, 2);
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $options = [];
            if ($method == "POST") {
                $options['form_params'] = $data;
            } else {
                $options['query'] = $data;
            }
            $response = $client->request($method, $url, $options);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['retCode'] != 'SUCCESS') {
                throw new \Exception($row['retMsg']);
            }

            return $row;
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBody = json_decode($response->getBody()->getContents());
                $message = $responseBody['msg'] ?? $e->getMessage();
            }

            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $filtered_params = array_filter($body, function ($value) {
            return $value !== '';
        });
        $signStr = urldecode(http_build_query($filtered_params)) . "&key=$key";
        return strtoupper(md5($signStr));
    }
}
