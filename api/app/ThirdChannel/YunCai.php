<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Utils\BCMathUtil;
use RuntimeException;

class YunCai extends ThirdChannel
{
    //Log名称
    public $log_name   = 'YunCai';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://deal.sbsdsdbaba.xyz/v3/server/deal';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://deal.sbsdsdbaba.xyz/louis/ap.do';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://bebepay.net/bebeApi/v1.1/orders/payment_transfer_query';
    public $queryBalanceUrl = 'https://deal.sbsdsdbaba.xyz/v3/server/balence';

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
        Channel::CODE_BANK_CARD => "BANK_R",
    ];

    public $bankMap = [
        "中国工商银行" => "001",
        "工商银行" => "001",
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "appId" => $data["merchant"],
            "orderId" => $data["request"]->order_number,
            'notifyUrl' => $data['callback_url'],
            "pageUrl" => "https://www.yahoo.com",
            'amount' => $data['request']->amount,
            "applyDate" => now()->format('YmdHis'),
            'passCode' => $this->channelCodeMap[$this->channelCode],
        ];

        $postBody['mcPayName'] = $data['request']->real_name ?? "王小明";

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage() ?? ""];
        }
        $info = $row["info"];

        $ret = [
            'pay_url'   => $info["payUrl"] ?? '',
            'receiver_name' => $info["account"] ?? "",
            'receiver_bank_name' => $info["accountName"] ?? "",
            'receiver_account' => $info["accountNo"] ?? "",
            'receiver_bank_branch' => $info["bankBranch"] ?? "",
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
        $math = new BCMathUtil;
        // $bankCode = $this->bankMap[$data['request']->bank_name];

        // if (!$bankCode) {
        //     return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        // }

        $postBody = [
            "version" => "1.0",
            "cid" => $data["merchant"],
            'tradeNo' => $data['request']->order_number,
            "requestTime" => date('YmdHis'),
            'amount' => $math->mul($data['request']->amount, 100, 0),  // 金額單位是分
            "payType" => 1,
            'acctName' => $data['request']->bank_card_holder_name,
            'acctNo' => $data['request']->bank_card_number,
            'bankCode' => $this->bankMap[$data["request"]->bank_name],
            'notifyUrl' => $data['callback_url'],
        ];

        $postBody["sign"] = $this->makesign($data, $data["key"]);

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false];
        }
        if ($result["retcode"] == 0) {
            return ["success" => true];
        } else {
            return ['success' => false];
        }
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data["apporderid"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], [2])) {
            return ['success' => true];
        }

        // //代付检查状态，失败
        // if (in_array($data["status"], [3])) {
        //     return ['fail' => '逾时'];
        // }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "userId" => $data["merchant"],
        ];

        $res = $this->sendRequest($data["queryBalanceUrl"], $postBody, false);

        $balance = $res["balance"];
        ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
            "balance" => $balance,
        ]);
        return $balance;
    }

    private function sendRequest($url, $data, $debug = true)
    {
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'json' => $data
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row["code"] != 0) {
                throw new RuntimeException($row["message"]);
            }

            return $row["data"];
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $e;
        }
    }

    public function makesign($body, $key)
    {
        unset($body["sign"]);
        ksort($body);
        $signStr = urldecode(http_build_query($body)) . $key;
        return md5($signStr);
    }
}
