<?php

namespace App\ThirdChannel;

use App\Model\Transaction;
use App\Model\Channel;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;
use GuzzleHttp\Client;

class BCPay extends ThirdChannel
{
    //Log名称
    public $log_name = "BCPay";
    public $type = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = "";
    public $depositUrl = "https://tt.pasdz.com/order/create";
    public $xiafaUrl = "https://tt.pasdz.com/payout/create";
    public $daifuUrl = "https://tt.pasdz.com/payout/create";
    public $queryDepositUrl = "https://tt.pasdz.com/transaction";
    public $queryDaifuUrl = "https://tt.pasdz.com/payout/status";
    public $queryBalanceUrl = "https://tt.pasdz.com/payout/balance";

    //默认商户号
    public $merchant = "";

    //默认密钥
    public $key = "";
    public $key2 = "";

    //回传字串
    public $success = "success";

    //白名单
    public $whiteIP = ["13.209.119.152"];

    public $channelCodeMap = [
        Channel::CODE_GCASH => 5,
        Channel::CODE_QR_GCASH => 5,
        Channel::CODE_MAYA => 2,
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $request = $data["request"];
        $secretData = [
            "merchantNo" => $data["merchant"],
            "userNo" => "",
            "discount" => "",
            "extra" => "",
            "orderNo" => $request->order_number,
            "amount" => $request->amount,
            "datetime" => date("Y-m-d H:i:s"),
            "notifyUrl" => $data["callback_url"],
            "time" => time()
        ];

        $postBody = array_merge($secretData, [
            "channelNo" =>
            $this->channelCodeMap[$request->channel_code],
            "appSecret" => $this->key,
            "sign" => $this->makesign($secretData)
        ]);

        try {
            $client = new Client();
            $response = $client->post($this->depositUrl, [
                "json" => $postBody,
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('postBody', 'message'));
            return [
                "success" => false
            ];
        }
        $row = json_decode($response->getBody(), true);
        if ($row["code"] == 1) {
            Log::error(self::class, compact("postBody", "response"));
            return [
                "success" => false,
            ];
        }

        return [
            "success" => true,
            "data" => [
                "order_number" => $request->order_number,
                "amount" => $request->amount,
                "pay_url" => $row["targetUrl"]
            ]
        ];
    }

    public function queryDeposit($data)
    {
        return ["success" => true, "msg" => "原因"];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $request = $data["request"];
        $secretData = [
            "merchantNo" => $data["merchant"],
            "orderNo" => $request->order_number,
            "amount" => $request->amount,
            "name" => $request->bank_card_holder_name,
            "bankName" => $request->bank_name,
            "bankAccount" => $request->bank_card_number,
            "bankBranch" => "",
            "memo" => "",
            "mobile" => "",
            "reverseUrl" => $data["callback_url"],
            "extra" => "",
            "datetime" => date("Y-m-d H:i:s"),
            "notifyUrl" => $data["callback_url"],
            "time" => time()
        ];

        $postBody = array_merge($secretData, [
            "appSecret" => $this->key,
            "sign" => $this->makesign($secretData)
        ]);

        try {
            $client = new Client();
            $response = $client->post($this->daifuUrl, [
                "json" => $postBody,
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('postBody', 'message'));
            return [
                "success" => false
            ];
        }
        $return_data = json_decode($response->getBody(), true);
        Log::debug(self::class, compact("secretData", "response"));

        if ($return_data["code"] == 1) {
            return ["success" => false];
        }

        return ["success" => true];
    }

    public function queryDaifu($data)
    {
        $this->key = $data["key"];

        $client = new Client();
        $secretData = [
            "merchantNo" => $data["merchant"],
            "orderNo" => $data["request"]->order_number,
            "time" => time()
        ];
        $postBody = array_merge($secretData, [
            "appSecret" => $this->key,
            "sign" => $this->makesign($secretData)
        ]);
        try {
            $response = $client->post($this->queryDaifuUrl, [
                "json" => $postBody,
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('postBody', 'message'));
            return [
                "success" => false,
            ];
        }
        $return_data = json_decode($response->getBody(), true);
        Log::debug(self::class . "/" . __METHOD__, compact("order_number", "response"));

        if ($return_data["code"] == 1) {
            return ["success" => false];
        }
        $status = $return_data["status"];
        if ($status == "PENDING") {
            return ["success" => true, "status" => Transaction::STATUS_PAYING];
        }
        if ($status == "PAID") {
            return ["success" => true, "status" => Transaction::STATUS_SUCCESS];
        }
        if ($status == "CANCELLED" || $status == "REVERSED") {
            return ["success" => false, "status" => Transaction::STATUS_FAILED];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();
        $signData = $request->all();
        unset($signData["sign"]);

        if ($data["sign"] != $this->makesign($signData)) {
            return ["error" => "签名不正确"];
        }

        if ($data["orderNo"] != $transaction->order_number) {
            return ["error" => "订单编号不正确"];
        }

        //代收、代付检查金额
        if (isset($data["amount"]) && $data["amount"] != $transaction->amount) {
            return ["error" => "金额不正确"];
        }

        //代收检查状态(代付成功狀態相同)
        if ($data["status"] == "PAID" || $data["status"] == "MANUAL PAID") {
            return ["success" => true];
        }

        //代付检查状态，失败
        if ($data["status"] == "CANCELLED") {
            return ["fail" => $data["reason"]];
        }

        return ["error" => "未知错误"];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $logName = self::class . "/" . __METHOD__;
        $client = new Client();
        $secretData = [
            "merchantNo" => $data["merchant"],
            "time" => time()
        ];
        $postBody = array_merge(
            $secretData,
            [
                "appSecret" => $this->key,
                "sign" => $this->makesign($secretData)
            ]
        );
        try {
            $response = $client->post($this->queryBalanceUrl, [
                "json" => $postBody
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error($logName, compact('postBody', 'message'));
            return 0;
        }
        $return_data = json_decode($response->getBody(), true);
        Log::debug($logName, compact("return_data"));

        if ($return_data["code"] == 1) {
            return 0;
        }
        $balance = $return_data["balance"] + $return_data["balance2"] + $return_data["balance3"] + $return_data["balance4"] + $return_data["balance5"];
        ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
            "balance" => $balance,
        ]);
        return $balance;
    }

    public function makesign($data)
    {
        ksort($data);
        $data = urldecode(http_build_query($data));
        $strSign = $data . $this->key2;
        $sign = strtoupper(md5(hash("sha256", $strSign)));
        return $sign;
    }
}
