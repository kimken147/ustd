<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class GOSM extends ThirdChannel
{
    //Log名称
    public $log_name   = 'GOSM';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://cv.gosm-pay.com/federer/api/v2/Collect';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://cv.gosm-pay.com/federer/api/v2/Pay';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://cv.gosm-pay.com/federer/api/v2/Order';
    public $queryBalanceUrl = 'https://cv.gosm-pay.com/federer/api/v2/Balance';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'Success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => 1,
        Channel::CODE_QR_ALIPAY => 2,
        Channel::CODE_QR_WECHATPAY => 3,
    ];

    public $bankMap = [
        // 主要銀行
        '中国银行' => 273,
        '中國銀行' => 273, // 繁體版本也映射到相同代碼
        '中国工商银行' => 274,
        '中国建设银行' => 275,
        '建设银行' => 275,
        '中国邮政储蓄银行' => 276,
        '中国农业银行' => 277,
        '中国民生银行' => 278,
        '民生银行' => 278,
        '交通银行' => 279,
        '招商银行' => 280,
        '中信银行' => 281,
        '上海浦东发展银行' => 282,
        '浦发银行' => 282,
        '兴业银行' => 283,
        '中国光大银行' => 284,
        '光大银行' => 284,
        '平安银行' => 285,
        '华夏银行' => 286,

        // 其他主要银行
        '北京银行' => 289,
        '上海银行' => 290,
        '江苏银行' => 291,
        '广发银行' => 292,
        '宁波银行' => 293,
        '厦门银行' => 294,
        '浙商银行' => 295,
        '渤海银行' => 296,
        '赣州银行' => 297,
        '南京银行' => 298,
        '盛京银行' => 299,
        '东莞银行' => 300,
        '九江银行' => 301,
        '广州银行' => 302,
        '广西北部湾银行' => 303,
        '中原银行' => 304,
        '天津银行' => 306,
        '台州银行' => 308,
        '宁夏银行' => 309,
        '汉口银行' => 310,
        '长安银行' => 311,
        '长沙银行' => 312,
        '成都银行' => 313,
        '江西银行' => 314,
        '齐鲁银行' => 315,
        '杭州银行' => 316,
        '河北银行' => 317,
        '邯郸银行' => 318,
        '哈尔滨银行' => 319,
        '恒丰银行' => 320,
        '贵阳银行' => 322,
        '郑州银行' => 323,
        '重庆银行' => 324,
        '桂林银行' => 325,
        '苏州银行' => 327,
        '富滇银行' => 328,
        '湖北银行' => 329,
        '徽商银行' => 330,
        '柳州银行' => 331,
        '珠海华润银行' => 333,
        '重庆三峡银行' => 335,
        '东营银行' => 336,
        '日照银行' => 338,
        '泸州市商业银行' => 339,
        '大连银行' => 340,
        '锦州银行' => 341,
        '青岛银行' => 343,
        '廊坊银行' => 344,
        '吉林银行' => 346,
        '西安银行' => 347,
        '莱商银行' => 348,
        '张家口银行' => 349,
        '鄂尔多斯银行' => 350,
        '四川天府银行' => 351,
        '沧州银行' => 352,
        '温州银行' => 354,
        '广东南粤银行' => 355,
        '龙江银行' => 356,
        '昆仑银行' => 357,
        '绍兴银行' => 358,
        '金华银行' => 362,
        '贵州银行' => 363,
        '营口银行' => 364,
        '邢台银行' => 365,
        '齐商银行' => 367,
        '晋商银行' => 369,
        '兰州银行' => 371,
        '山西银行' => 373,
        '广东华兴银行' => 375,
        '晋城银行' => 378,
        '湖州银行' => 379,
        '中旅银行' => 380,
        '福建海峡银行' => 381,
        '葫芦岛银行' => 382,
        '青海银行' => 386,
        '甘肃银行' => 387,
        '保定银行' => 388,
        '石嘴山银行' => 391,
        '四川银行' => 393,
        '丹东银行' => 395,
        '唐山银行' => 397,
        '浙江稠州商业银行' => 400,
        '乌鲁木齐银行' => 402,
        '浙江民泰商业银行' => 403,
        '浙江泰隆商业银行' => 404,
        '乐山市商业银行' => 405,
        '哈密市商业银行' => 414,

        // 农村信用社系统
        '湖北省农村信用社' => 415,
        '山东省农村信用社' => 416,
        '广东省农村信用社联合社' => 417,
        '云南省农村信用社' => 418,
        '四川省农村信用社' => 419,
        '吉林省农村信用社' => 420,
        '安徽省农村信用社' => 421,
        '江西省农村信用社联合社' => 422,
        '江苏省农村信用社联合社' => 423,
        '贵州省农村信用社' => 424,
        '浙江省农村信用社联合社' => 425,
        '海南省农村信用社' => 426,
        '黑龙江省农村信用社' => 427,
        '福建省农村信用社联合社' => 428,
        '河南省农村信用社' => 429,
        '山西省农村信用社联合社' => 430,
        '湖南省农村信用社联合社' => 431,
        '河北省农村信用社' => 432,
        '陕西省农村信用社联合社' => 433,
        '辽宁省农村信用社' => 434,
        '甘肃省农村信用社' => 435,
        '青海省农村信用社' => 436,
        '宁夏农村信用合作社' => 437,
        '内蒙古农村信用社' => 438,
        '新疆农村信用社联合社' => 439,
        '广西壮族自治区农村信用社联合社' => 440,

        // 农村商业银行
        '上海农村商业银行' => 441,
        '昆山农村商业银行' => 442,
        '北京农村商业银行' => 443,
        '东莞农村商业银行' => 444,
        '深圳农村商业银行' => 445,
        '成都农村商业银行' => 446,
        '重庆农村商业银行' => 447,
        '天津农村商业银行' => 449,
        '江南农村商业银行' => 450,
        '武汉农村商业银行' => 451,
        '江阴农商银行' => 457,
        '珠海农商银行' => 458,
        '哈尔滨农商银行' => 459,
        '宁夏黄河农村商业银行' => 464,
        '天津滨海农商银行' => 468,
        '广州农村商业银行' => 472,
        '广东顺德农村商业银行' => 473,
        '江苏农村商业银行' => 480,
        '江苏常熟农村商业银行' => 482,

        // 村镇银行
        '中银富登村镇银行' => 483,
        '乾县中银富登村镇银行' => 483,
        '北京中银富登村镇银行' => 483,
        '深圳南山宝生村镇银行' => null,
        '深圳福田银座村镇银行' => 507,
        '浙江网商银行' => 529,
        '富邦华一银行' => 531,
        '厦门国际银行' => 532,
        '韩亚银行' => 538,
        '支付宝' => 0
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "account" => $data["merchant"],
            'amount' => floatval($data['request']->amount),
            'storeOrderCode' => $data['request']->order_number,
            'payName' => $data['request']->real_name ?: Str::random(4),
            'userIp' => $data['request']->client_ip ?: '168.168.168.168',
            'storeUrl' => $data['callback_url'],
            "series" => $this->channelCodeMap[$this->channelCode],
            'submitTime' => now()->timestamp,
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }

        $ret = [
            'pay_url'   => $row['FullUrl'] ?? '',
        ];
        return ['success' => true, 'data' => $ret];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $bankCode = $this->bankMap[$this->normalizeChineseCharacters($data['request']->bank_name)] ?? null;
        if (!$bankCode) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $postBody = [
            "account" => $data["merchant"],
            'amount' => floatval($data['request']->amount),
            'colAccount' => $data['request']->bank_card_number,
            'colBankId' => $bankCode,
            "colName" => $data['request']->bank_card_holder_name,
            'storeOrderCode' => $data['request']->order_number,
            'storeUrl' => $data['callback_url'],
            'series' => $bankCode == 0 ? 2 : 1,
            'userIp' => '127.0.0.1',
            'submitTime' => now()->timestamp,
        ];

        if ($bankCode != 0) {
            $postBody['colBankBranch'] = '空';
        }

        try {
            $result = $this->sendRequest($data["url"], $postBody);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postBody = [
            "account" => $data["merchant"],
            'storeOrderCode' => $data['request']->order_number,
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        // $sign = $this->makesign($data, $thirdChannel->key);

        // if ($sign != $data["sign"]) {
        //     return ["error" => "签名不正确"];
        // }

        if ($data["storeOrderCode"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], ['COMPLETED'])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], ['FAIL', 'REFUND'])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "account" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, 'GET', false);
            $balance = $row['amount'] - $row['fronzenAmount'];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            Log::error(self::class, compact('data', 'message'));
            return 0;
        }
    }

    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $data["signedMsg"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $options = $method === "GET"
                ? ["query" => $data]  // GET 方法使用 query 參數
                : ["json" => $data];  // POST 方法使用 json 參數

            $response = $client->request($method, $url, $options);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row["code"] != 0) {
                throw new Exception($row["message"]);
            }

            return $row['result'];
        } catch (RequestException $e) {
            $errorBody = $e->hasResponse() ? json_decode($e->getResponse()->getBody(), true) : null;
            Log::error(self::class, compact('data', 'errorBody'));
            throw new Exception($errorBody['message'] ?? $e->getMessage());
        } catch (Exception $e) {
            Log::error(self::class, compact('data', 'e'));
            throw new Exception($e->getMessage());
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = urldecode(http_build_query($body) . "$key");
        $signStr = hash('sha256', hash('sha256', $signStr) . $key);
        return $signStr;
    }
}
