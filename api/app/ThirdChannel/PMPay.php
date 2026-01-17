<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Carbon\Carbon;

class PMPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'PMPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://www.bnvr02.com/federer/api/v2/Collect';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://www.bnvr02.com/federer/api/v2/Pay';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = '';
    public $queryBalanceUrl = 'https://www.bnvr02.com/federer/api/v2/Balance';

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
        Channel::CODE_BANK_CARD => "BankToBank",
        Channel::CODE_QR_ALIPAY => "930"
    ];

    public $bankMap = [
        "中国工商银行" => 274,
        "工商银行" => 274,
        "中国建设银行" => 275,
        "中国建设" => 275,
        "建设银行" => 275,
        "中国农业银行" => 277,
        "农业银行" => 277,
        "中国邮政储蓄银行" => 276,
        "邮政银行" => 276,
        "中国邮政" => 276,
        "中国光大银行" => 284,
        "光大银行" => 284,
        "招商银行" => 280,
        "交通银行" => 279,
        "中信银行" => 281,
        "兴业银行" => 283,
        "中国银行" => 273,
        "中国民生银行" => 278,
        "民生银行" => 278,
        "华夏银行" => 286,
        "广发银行" => 292,
        "平安银行" => 285,
        "北京银行" => 289,
        "上海银行" => 290,
        "南京银行" => 298,
        "渤海银行" => 296,
        "宁波银行" => 293,
        "上海农村商业银行" => 441,
        "浙商银行" => 295,
        "徽商银行" => 330,
        "广州银行" => 302,
        "长沙银行" => 312,
        "青岛银行" => 343,
        "天津银行" => 306,
        "恒丰银行" => 320,
        "成都农村商业银行" => 446,
        "浙江民泰商业银行" => 403,
        "泰隆银行" => 404,
        "福建海峡银行" => 381,
        "盛京银行" => 299,
        "莱商银行" => 348,
        "郑州银行" => 323,
        "上海浦东发展银行" => 282,
        "浦发银行" => 282,
        "厦门银行" => 294,
        "桂林银行" => 325,
        "广西北部湾银行" => 303,
        "浙江省农村信用社联合社" => 425,
        "浙江省农村信用社" => 425,
        "浙江省农信" => 425,
        "浙江农村信用社联合社" => 425,
        "浙江农村信用社" => 425,
        "浙江农信" => 425,
        "南宁江南国民村镇银行" => 489,
        "重庆农村商业银行" => 447,
        "重庆农商" => 447,
        "山东省农村信用社联合社" => 416,
        "山东省农村信用社" => 416,
        "山东省农信" => 416,
        "山东农村信用社联合社" => 416,
        "山东农村信用社" => 416,
        "山东农信" => 416,
        "柳州银行" => 331,
        "中原银行" => 304,
        "乐山市商业银行" => 405,
        "乐山市商银" => 405,
        "河南省农村信用社联合社" => 429,
        "河南省农村信用社" => 429,
        "河南省农信" => 429,
        "河南农村信用社联合社" => 429,
        "河南农村信用社" => 429,
        "河南农信" => 429,
        "四川天府银行" => 351,
        "广西壮族自治区农村信用社联合社" => 440,
        "广西壮族自治区农村信用社" => 440,
        "广西壮族自治区农信" => 440,
        "广西自治区农村信用社联合社" => 440,
        "广西自治区农村信用社" => 440,
        "广西自治区农信" => 440,
        "广西农村信用社" => 440,
        "广西农信" => 440,
        "福建省农村信用社联合社" => 428,
        "福建省农村信用社" => 428,
        "福建省农信" => 428,
        "福建农村信用社联合社" => 428,
        "福建农村信用社" => 428,
        "福建农信" => 428,
        "湖南省农村信用社联合社" => 431,
        "湖南省农村信用社" => 431,
        "湖南省农信" => 431,
        "湖南农村信用社联合社" => 431,
        "湖南农村信用社" => 431,
        "湖南农信" => 431,
        "湖北省农村信用社联合社" => 415,
        "湖北省农村信用社" => 415,
        "湖北省农信" => 415,
        "湖北农村信用社联合社" => 415,
        "湖北农村信用社" => 415,
        "湖北农信" => 415,
        "张家口银行" => 349,
        "晋中银行" => null,
        "晋城银行" => 378,
        "银座银行" => null,
        "安徽省农村信用社联合社" => 421,
        "安徽省农村信用社" => 421,
        "安徽省农信" => 421,
        "安徽农村信用社联合社" => 421,
        "安徽农村信用社" => 421,
        "安徽农信" => 421,
        "安徽信用社联合社" => 421,
        "安徽信用社" => 421,
        "广州省农村商业银行" => 472,
        "广州农村商业银行" => 472,
        "广州农商银行" => 472,
        "广州农商" => 472,
        "广州商银" => 472,
        "东莞农商银行" => 444,
        "东莞农商" => 444,
        "深圳农村商业银行" => 445,
        "深圳农商银行" => 445,
        "深圳农商" => 445,
        "顺德农商商业银行" => 473,
        "顺德农商银行" => 473,
        "顺德农商" => 473,
        "河南伊川农商银行" => null,
        "河南伊川农商" => null,
        "广东省农村信用社联合社" => 417,
        "广东省农村信用社" => 417,
        "广东省农信" => 417,
        "广东农村信用社联合社" => 417,
        "广东农村信用社" => 417,
        "广东农信" => 417,
        "四川省农村信用社联合社" => 419,
        "四川省农村信用社" => 419,
        "四川省农信" => 419,
        "四川农村信用社联合社" => 419,
        "四川农村信用社" => 419,
        "四川农信" => 419,
        "江西省农村信用社联合社" => 422,
        "江西省农村信用社" => 422,
        "江西省农信" => 422,
        "江西农村信用社联合社" => 422,
        "江西农村信用社" => 422,
        "江西农信" => 422,
        "珠海市农村信用社联合社" => null,
        "珠海市农村信用社" => null,
        "珠海市农信" => null,
        "珠海农村信用社联合社" => null,
        "珠海农村信用社" => null,
        "珠海农信" => null,
        "云南省农村信用社联合社" => 418,
        "云南省农村信用社" => 418,
        "云南省农信" => 418,
        "云南农村信用社联合社" => 418,
        "云南农村信用社" => 418,
        "云南农信" => 418,
        "重庆银行" => 324,
        "贵州省农村信用社联合社" => 424,
        "贵州省农村信用社" => 424,
        "贵州省农信" => 424,
        "贵州农村信用社联合社" => 424,
        "贵州农村信用社" => 424,
        "贵州农信" => 424,
        "珠海农商银行" => 458,
        "珠海农商" => 458,
        "广东南粤银行" => 355,
        "中旅银行" => 380,
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "account" => $data["merchant"],
            'amount' => floatval($data['request']->amount),
            'payName' => $data['request']->real_name ?? '王小明',
            'userIp' => $data['request']->client_ip ?? '1.1.1.1',
            'storeOrderCode' => $data['order_number'],
            'storeUrl' => $data['callback_url'],
            "series" => intval($data["key2"]),
            'submitTime' => time()
        ];

        $postBody['signedMsg'] = $this->makeSign($postBody, $this->key, 2);

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            $ret = [
                'pay_url' => $row["FullUrl"] ?? '',
                'receiver_account' => $row["CollectAccount"],
                'receiver_name' => $row["CollectName"] ?? '',
                'receiver_bank_branch' => $row["subBank"] ?? '',
                'receiver_bank_name' => $row["bankName"] ?? '',
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
        if ($data['request']->bank_name != '支付宝') {
            return ['success' => false, 'msg' => '不支持銀行代付'];
        }

        $this->key = $data['key'];
        $body = [
            'account' => $data['merchant'],
            'amount' => floatval($data['request']->amount),
            'colAccount' => $data['request']->bank_card_number,
            'colBankId' => 0,
            'colName' => $data['request']->bank_card_holder_name,
            'storeOrderCode' => $data['order_number'],
            'storeUrl' => $data['callback_url'],
            'series' => 2,
            'userIp' => $data['request']->client_ip ?? '1.1.1.1',
            'submitTime' => time(),
        ];
        $body['signedMsg'] = $this->makeSign($body, $data['key'], 2);

        try {
            $res = $this->sendRequest($data["url"], $body);
            return ['success' => true];
        } catch (\Throwable $th) {
            return ['success' => false, 'msg' => $th->getMessage()];
        }
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if (($data["storeOrderCode"] != $transaction->order_number) && ($data["storeOrderCode"] != $transaction->system_order_number)) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data["status"] == "COMPLETED") {
            return ['success' => true];
        }

        if (in_array($data["status"], ['MATCHFAIL', 'FAIL', 'REFUND']) && in_array($transaction->type, [2, 4])) {
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
        $postBody['signedMsg'] = $this->makeSign($postBody, $data['key'], 2);

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "GET", false);
            $balance = $row["amount"];
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
        try {
            $client = new Client();
            $options = [];
            if ($method == "POST") {
                $options['json'] = $data;
            } else {
                $options['query'] = $data;
            }
            $response = $client->request($method, $url, $options);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['code'] != '0') {
                throw new Exception($row['message']);
            }

            return $row['result'];
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

    public function makesign($body, $key, $mode = 1)
    {
        ksort($body);
        $signStr = urldecode(http_build_query($body) . "$key");
        $sha256 = hash("sha256", $signStr);
        if ($mode === 1) {
            return hash('sha3-256', $sha256 . $key);
        } else {
            return hash('sha256', $sha256 . $key);
        }
    }
}
