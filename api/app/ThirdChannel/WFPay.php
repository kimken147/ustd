<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class WFPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'WFPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://mwifuswzv.com/merchant_api/v1/orders/payment';
    public $xiafaUrl = 'https://mwifuswzv.com/api/payfor/trans';
    public $daifuUrl = 'https://mwifuswzv.com/merchant_api/v1/orders/payment_transfer';
    public $queryDepositUrl = 'https://mwifuswzv.com/api/pay/orderquery';
    public $queryDaifuUrl = 'https://mwifuswzv.com/merchant_api/v1/orders/payment_transfer_query';
    public $queryBalanceUrl = 'https://mwifuswzv.com/merchant_api/v1/balances/query';
    public $rematchUrl = 'https://mwifuswzv.com/merchant_api/v1/orders/rematch';
    public $alipayDaifuUrl = "https://mwifuswzv.com/merchant_api/v1/orders/alipay_payment_transfer";

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
        Channel::CODE_BANK_CARD => 1,
        Channel::CODE_QR_ALIPAY => 3,
        Channel::CODE_ZH_ALIPAY => 4,
        Channel::CODE_UNION_QUICK_PASS => 1
    ];

    public $bankMap = [
        "中国工商银行" => "ICBC",
        "工商银行" => "ICBC",
        "中国建设银行" => "CCB",
        "中国建设" => "CCB",
        "建设银行" => "CCB",
        "中国农业银行" => "ABCHINA",
        "农业银行" => "ABCHINA",
        "中国邮政储蓄银行" => "PSBC",
        "邮政银行" => "PSBC",
        "中国邮政" => "PSBC",
        "中国光大银行" => "ChinaEverbrightBank",
        "光大银行" => "HPTChinaEverbrightBank00022",
        "招商银行" => "CMBCHINA",
        "交通银行" => "BANKCOMM",
        "中信银行" => "CHINACITICBANK",
        "兴业银行" => "CIB",
        "中国银行" => "BANKOFCHINA",
        "中国民生银行" => "CMBC",
        "民生银行" => "CMBC",
        "华夏银行" => "HUAXIABANK",
        "广发银行" => "CGB",
        "平安银行" => "PINGANBANK",
        "北京银行" => "BEIJING",
        "上海银行" => "BANKOFSHANGHAI",
        "南京银行" => "NJCB",
        "渤海银行" => "CBHB",
        "宁波银行" => "NBCB",
        "上海农村商业银行" => "SRCB",
        "浙商银行" => "CZBANK",
        "徽商银行" => "HSBANK",
        "广州银行" => "GZCB",
        "长沙银行" => "CSYH",
        "青岛银行" => "QDCCB",
        "天津银行" => "BANKOFTIANJIN",
        "成都农村商业银行" => "CDRCB",
        "泰隆银行" => "ZJTLCB",
        "盛京银行" => "SHENGJINGBANK",
        "郑州银行" => "ZZBANK",
        "上海浦东发展银行" => "SPDBANK",
        "浦发银行" => "SPDBANK",
        "厦门银行" => "XMCCB",
        "桂林银行" => "GUILINBANK",
        "广西北部湾银行" => "CORPORBANK",
        "浙江省农村信用社" => "ZJ96596",
        "浙江农信" => "ZJ96596",
        "重庆农村商业银行" => "CQRCB",
        "山东省农村信用社联合社" => "SDRCU",
        "山东农村信用社" => "SDRCU",
        "柳州银行" => "LZCCB",
        "河南省农村信用社" => "HNNX",
        "四川天府银行" => "TFB",
        "广西壮族自治区农村信用社联合社" => "GX966888",
        "广西农村信用社" => "GX966888",
        "广西自治区农村信用社" => "GX966888",
        "福建省农村信用社联合社" => "FJNX",
        "福建省农村信用社" => "FJNX",
        "湖南省农村信用社联合社" => "HNNXS",
        "湖南省农村信用社" => "HNNXS",
        "安徽信用社" => "AHRCU",
        "广州农商银行" => "GRCBANK",
        "广州省农村商业银行" => "GRCBANK",
        "东莞农商银行" => "DRCBANK",
        "东莞农商" => "DRCBANK",
        "深圳农商银行" => "4001961200",
        "深圳农村商业银行" => "4001961200",
        "顺德农商银行" => "SDEBANK",
        "广东农村信用社" => "GDRC",
        "四川省农村信用社" => "SCRCU",
        "云南农村信用社" => "YNRCC",
        "云南省农村信用社联合社" => "YNRCC",
        "重庆银行" => "CQCBANK",
        "贵州省农村信用社" => "GZNXBANK",
        "支付宝" => "支付宝"
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "account_name" => $data["merchant"],
            'merchant_order_id' => $data['system_order_number'],
            'total_amount' => $data['request']->amount,
            "timestamp" => now()->format("c"),
            'notify_url' => $data['callback_url'],
            'payment_method' => $data['key3'] ?: "alipay",
            "subject" => "deposit",
            'force_matching' => true
        ];

        if ($data['request']->real_name) {
            $postBody['guest_real_name'] = $data['request']->real_name;
        }

        try {
            $row = $this->sendRequest($data["url"], $postBody);

            // 如果狀態是 pending_allocation，開始輪詢
            if ($row["status"] == "pending_allocation") {
                $startTime = time();
                $maxWaitTime = 60; // 最長等待一分鐘

                do {
                    try {
                        $row = $this->sendRequest($data["rematchUrl"], [
                            "account_name" => $data["merchant"],
                            'merchant_order_id' => $data["system_order_number"],
                            'timestamp' => now()->format("c"),
                        ]);

                        // 如果不再是 pending_allocation，跳出迴圈
                        if ($row["status"] != "pending_allocation") {
                            break;
                        }

                        // 檢查是否超時
                        if (time() - $startTime >= $maxWaitTime) {
                            return ["success" => false, 'msg' => '輪詢超時，請稍後再試'];
                        }

                        sleep(5);

                    } catch (\Throwable $th) {
                        // 輪詢過程中發生錯誤，記錄日誌但繼續嘗試
                        Log::warning('輪詢過程中發生錯誤: ' . $th->getMessage());

                        // 如果已經接近超時，就不再重試
                        if (time() - $startTime >= $maxWaitTime - 5) {
                            return ["success" => false, 'msg' => '輪詢過程中發生錯誤且接近超時'];
                        }

                        sleep(5);
                        continue;
                    }
                } while ($row["status"] == "pending_allocation" && (time() - $startTime) < $maxWaitTime);

                // 超時後仍然是 pending_allocation
                if ($row["status"] == "pending_allocation") {
                    return ["success" => false, 'msg' => '處理超時，狀態仍為待分配'];
                }

                if ($row["status"] == "failed") {
                    return ["success" => false, 'msg' => '配對失敗'];
                }
            }

        } catch (\Throwable $th) {
            return ["success" => false, 'msg' => $th->getMessage()];
        }

        $accountName = $row['account_name'] ?? '';
        $account = $row['bank_account'] ?? '';
        if (isset($row["payment_info"])) {
            $paymentInfo = $row["payment_info"];
            $account = $paymentInfo['account_name'] ?? '';
            $accountName = $paymentInfo['surname'] ?? $paymentInfo['name'] ?? '';
        }

        $ret = [
            'pay_url' => $row['payment_url'] ?? '',
            'receiver_name' => $accountName,
            'receiver_bank_name' => $row['bank_name'] ?? '',
            'receiver_account' => $account,
            'receiver_bank_branch' => $row['bank_branch_name'] ?? '',
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
//        $bankCode = $this->bankMap[$data['request']->bank_name] ?? null;
//
//        if (!$bankCode) {
//            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
//        }

        $province = "empty";
        $city = "empty";

        if ($data['request']->bank_province) {
            $province = $data['request']->bank_province;
        }
        if ($data['request']->bank_city) {
            $city = $data['request']->bank_city;
        }

        $postBody = [
            "account_name" => $data["merchant"],
            'merchant_order_id' => $data['system_order_number'],
            'total_amount' => $data['request']->amount,
            "timestamp" => now()->format("c"),
            'notify_url' => $data['callback_url'],
        ];


        if ($data['request']->bank_name == '支付宝') {
            $url = $data["alipayDaifuUrl"];
            $postBody = array_merge($postBody, [
                'alipay_account_name' => $data['request']->bank_card_number,
                'alipay_surname' => mb_substr($data['request']->bank_card_holder_name, 0, 1, "UTF-8"),
            ]);
        } else {
            $url = $data["url"];
            $postBody = array_merge($postBody, [
                'bank_name' => $data["request"]->bank_name,
                "bank_province_name" => $province,
                "bank_city_name" => $city,
                'bank_account_no' => $data['request']->bank_card_number,
                "bank_account_type" => "personal",
                'bank_account_name' => $data['request']->bank_card_holder_name,
            ]);
        }

        try {
            Log::debug('WFPAY payout', compact('url', 'postBody'));
            $this->sendRequest($url, $postBody);
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
        return ["success" => true];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "account_name" => $data["merchant"],
            'merchant_order_id' => $data['system_order_number'],
            "timestamp" => now()->format("c"),
        ];

        try {
            $this->sendRequest($data['queryDaifuUrl'], $postBody);
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }

        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if (!$this->verifySign($data, $thirdChannel->key2)) {
            return ["error" => "签名错误"];
        }

        $data = json_decode($data["data"]);
        $order = $data->order;

        if (($order->merchant_order_id != $transaction->order_number) && ($order->merchant_order_id != $transaction->system_order_number)) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($order->total_amount != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data->notify_type, ["trade_completed", "payment_transfer_completed"])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data->notify_type, ["payment_transfer_failed"])) {
            return ['fail' => '逾时'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "account_name" => $data["merchant"],
            "timestamp" => now()->format("c"),
        ];

        $row = $this->sendRequest($data['queryBalanceUrl'], $postBody, false);
        $balance = $row["balance"];
        ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
            "balance" => $balance,
        ]);
        return $balance;
    }

    private function sendRequest($url, $data, $debug = true)
    {
        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'form_params' => [
                    "data" => json_encode($data),
                    "signature" => $this->makesign($data, $this->key)
                ],
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = json_decode($response->getBody(), true) ?? [];
                $message = $body['message'] ?? $e->getMessage();
                Log::error(self::class, compact('data', 'body', 'message'));
                throw $e;
            } else throw $e;
        }

    }

    private function verifySign($data, $key)
    {
        $json = $data["data"];
        $pubKey = openssl_get_publickey($key);
        $verified = openssl_verify($json, base64_decode($data["signature"]), $pubKey, OPENSSL_ALGO_SHA256);
        return $verified != 0;
    }

    public function makesign($body, $key)
    {
        unset($body["sign"]);
        $json = json_encode($body);
        $priKey = openssl_pkey_get_private($this->key);
        openssl_sign($json, $signature, $priKey, OPENSSL_ALGO_SHA256);
        return base64_encode($signature);
    }
}
