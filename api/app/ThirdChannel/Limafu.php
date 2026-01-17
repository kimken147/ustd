<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class Limafu extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Limafu';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'http://api.ponypay1.com/apinow/deposit.ashx';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://apii.dkk888.com/api/payments/pay_order';
    public $queryDepositUrl    = 'https://mwifuswzv.com/api/pay/orderquery';
    public $queryDaifuUrl  = 'https://mwifuswzv.com/merchant_api/v1/orders/payment_transfer_query';
    public $queryBalanceUrl = 'https://api.ponypay1.com/apinow/getbalance.ashx';

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
        Channel::CODE_BANK_CARD => "CNY-BANK2BANK",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "merchant_id" => $data["merchant"],
            'merchant_orderid' => $data['request']->order_number,
            'money' => $data['request']->amount,
            'notifyurl' => $data['callback_url'],
            'paytype' => $this->channelCodeMap[$this->channelCode],
            "merchant_para" => time()
        ];


        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['realname'] = $data['request']->real_name;
        }

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false];
        }

        if ($row["status"] == 1) {
            $info = $row["data"];
            $ret = [
                'pay_url'   => $info["url"] ?? '',
                'receiver_name' => $info["bankusername"] ?? null,
                'receiver_bank_name' => $info["bankname"] ?? null,
                'receiver_account' => $info["bankcode"] ?? null,
                'receiver_bank_branch' => $info["bankaddress"] ?? null,
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ["success" => false, "msg" => $row["message"] ?? ""];
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

        $postBody = [
            "pay_customer_id" => $data["merchant"],
            "pay_apply_date" => time(),
            'pay_order_id' => $data['request']->order_number,
            'pay_notify_url' => $data['callback_url'],
            'pay_amount' => $data['request']->amount,
            "pay_account_name" => $data['request']->bank_card_holder_name,
            "pay_card_no" => $data['request']->bank_card_number,
            "pay_bank_name" => $data["request"]->bank_name
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }
        if ($result["code"] == 0) {
            return ["success" => true];
        } else {
            return ["success" => false, "msg" => $result["message"] ?? ""];
        }
        return ["success" => false];
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
        if ($data["sign"] != $sign) {
            return ["error" => "簽名錯誤"];
        }

        if ($data["merchant_orderid"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ((isset($data["money"]) && $data["money"] != $transaction->amount)) {
            return ['error' => '金额不正确'];
        }

        //代收检查状态
        if (isset($data["status"]) && in_array($data["status"], ["1"])) {
            return ['success' => true];
        }

        // if ((isset($data["status"]) && in_array($data["status"], ["50000"])) || (isset($data["transaction_code"]) && in_array($data["transaction_code"], ["40000"]))) {
        //     return ['fail' => '逾时'];
        // }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant_id" => $data["merchant"],
            "datetime" => now()->format("Y-m-d H:i:s"),
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "form_params", false);
            if ($row["status"] == 1) {
                $balance = $row["data"][0]["money"];
                ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                    "balance" => $balance,
                ]);
                return $balance;
            }
            return 0;
        } catch (\Throwable $th) {
            return 0;
        }
    }

    private function sendRequest($url, $data, $type = "form_params", $debug = true)
    {
        try {
            $client = new Client();
            $data["sign"] = $this->makesign($data, $this->key);
            $response = $client->request('POST', $url, [
                $type => $data
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
        $signStr = urldecode(http_build_query($body)) . $key;
        return md5($signStr);
    }
}
