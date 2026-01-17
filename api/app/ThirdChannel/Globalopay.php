<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use App\Model\Transaction;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;
use GuzzleHttp\Client;

class Globalopay extends ThirdChannel
{
    // 文檔
    // https://glpay168.com/docs
    //Log名称
    public $log_name = "Globalopay";
    public $type = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify = "";
    public $depositUrl = "https://glpay168.com/api/transaction";
    public $xiafaUrl = "https://glpay168.com/api/payment";
    public $daifuUrl = "https://glpay168.com/api/payment";
    public $queryDepositUrl = "https://glpay168.com/api/transaction";
    public $queryDaifuUrl = "https://glpay168.com/api/payment";
    public $queryBalanceUrl = "https://glpay168.com/api/balance/inquiry";

    //默认商户号
    public $merchant = "";

    //默认密钥
    public $key = "";
    public $key2 = "";

    //回传字串
    public $success = "ok";

    //白名单
    public $whiteIP = ["13.209.119.152"];

    public $channelCodeMap = [
        "BANK_CARD" => "bankcard",
    ];

    private function getHeaders()
    {
        return [
            "Content-Type" => "application/json",
            "Accept" => "application/json",
            "Authorization" => "Bearer " . $this->key,
        ];
    }

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data["key"];
        $post = [
            "amount" => $data["request"]->amount,
            "callback_url" => $data["callback_url"],
            "out_trade_no" => $data["request"]->order_number,
        ];
        $client = new Client();
        $res = $client->post($this->depositUrl, [
            "headers" => $this->getHeaders(),
            "json" => $post,
        ]);

        $row = json_decode($res->getBody(), true);
        Log::debug(self::class, compact("post", "row"));

        if (isset($row["success"]) && $row["success"]) {
            $ret = [
                "order_number" => $data["request"]->order_number,
                "amount" => $row["data"]["amount"],
                "pay_url" => $row["data"]["uri"],
            ];
            return ["success" => true, "data" => $ret];
        }
        return ["success" => false];
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
        $client = new Client();

        $post_data = [
            "out_trade_no" => $data["request"]->order_number,
            "bank_id" => "GCASH",
            "bank_owner" => $data["request"]->bank_card_holder_name,
            "account_number" => $data["request"]->bank_card_number,
            "amount" => $data["request"]->amount,
            "callback_url" => $data["callback_url"],
        ];

        $post_data["sign"] = $this->makesign($post_data);
        try {
            $response = $client->post($this->daifuUrl, [
                "headers" => $this->getHeaders(),
                "json" => $post_data,
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact("post_data", "message"));
            return [
                "success" => false,
            ];
        }
        $return_data = json_decode($response->getBody(), true);
        Log::debug(self::class, compact("post_data", "return_data"));
        if (!$return_data["success"]) {
            return ["success" => false];
        }

        return $this->getState($return_data);
    }

    public function queryDaifu($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $client = new Client(["headers" => $this->getHeaders()]);
        $orderNumber = $data["request"]->order_number;
        $url = $this->queryDaifuUrl . "/$orderNumber";
        try {
            $response = $client->request("GET", $url);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact("orderNumber", "message"));
            return [
                "success" => false,
            ];
        }
        $return_data = json_decode($response->getBody(), true);
        Log::debug(self::class, compact("orderNumber", "return_data"));
        if (!$return_data["success"]) {
            return ["success" => false];
        }

        return $this->getState($return_data);
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback(
        Request $request,
        Transaction $transaction,
        ThirdChannelModel $thirdChannel
    ) {
        $this->key = $thirdChannel->key;
        $this->key2 = $thirdChannel->key2;
        $data = $request->all();
        $signData = $request->except(["sign", "callback_url"]);
        $sign = $this->makesign($signData);

        if ($sign !== $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if ($data["out_trade_no"] != $transaction->order_number) {
            return ["error" => "订单编号不正确"];
        }

        //代收检查金额
        if (isset($data["amount"]) && $data["amount"] != $transaction->amount) {
            return ["error" => "金额不正确"];
        }

        //代收、代付检查状态
        if (isset($data["state"]) && $data["state"] == "completed") {
            return ["success" => true];
        }

        //代付检查状态，失败
        if (
            isset($data["state"]) &&
            in_array($data["state"], ["refund", "failed"])
        ) {
            return ["fail" => "支付失败"];
        }

        return ["error" => "未知错误"];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $this->key2 = $data["key2"];
        $client = new Client(["headers" => $this->getHeaders()]);
        try {
            $response = $client->request("GET", $this->queryBalanceUrl);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact("message"));
            return 0;
        }
        $return_data = json_decode($response->getBody(), true);

        // Log::debug(self::class . "/queryBalance", compact("return_data"));

        if (
            isset($return_data["success"]) &&
            in_array($return_data["success"], [true])
        ) {
            $balance = $return_data["data"]["balance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data)
    {
        ksort($data);
        $data = urldecode(http_build_query($data));
        $strSign = $data . $this->key . $this->key2;
        $sign = md5($strSign);
        return $sign;
    }

    private function getState($return_data)
    {
        if (is_null($return_data)) {
            return ["success" => false, "status" => Transaction::STATUS_FAILED];
        }
        $data = $return_data["data"];
        if (!isset($data["state"])) {
            return ["success" => false];
        }
        if (in_array($data["state"], ["new", "processing"])) {
            return ["success" => true, "status" => Transaction::STATUS_PAYING];
        } elseif (in_array($data["state"], ["completed"])) {
            return ["success" => true, "status" => Transaction::STATUS_SUCCESS];
        } else {
            return ["success" => false, "status" => Transaction::STATUS_FAILED];
        }
    }
}
