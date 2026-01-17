<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use App\Utils\BCMathUtil;

class LHPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'LHPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://www.lihui13198.com/pay';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://www.lihui13198.com/transfer/apply';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = '';
    public $queryBalanceUrl = 'https://www.lihui13198.com/merchant/balance';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [

    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            'amount' => $data['request']->amount,
            "merchant" => $data["merchant"],
            'paytype' => $data['key2'],
            'outtradeno' => $data['request']->order_number,
            'notifyurl' => $data['callback_url'],
            'returnurl' => 'https://www.baidu.com',
            "returndataformat" => "serverhtml",
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['payername'] = $data['request']->real_name;
        }

        $postBody["sign"] = $this->makesign($postBody, $data["key"]);

        try {
            $row = $this->sendRequest($data["url"], $postBody);
        } catch (\Throwable $th) {
            return ["success" => false];
        }

        if ($row["code"] == 0) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $data['request']->amount,
                'pay_url'   => $row['results'] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        }

        return ["success" => false, "msg" => $row["results"] ?? ""];
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
        $bankCode = $this->bankMap[$data['request']->bank_name];

        if (!$bankCode) {
            return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
        }

        $postBody = [
            'amount' => $data['request']->amount,  // 金額單位是分
            "merchant" => $data["merchant"],
            'bankname' => $data["request"]->bank_name,
            'cardno' => $data['request']->bank_card_number,
            'cardname' => $data['request']->bank_card_holder_name,
            'notifyurl' => $data['callback_url'],
            'returnurl' => 'https://www.baidu.com',
            'outtransferno' => $data['request']->order_number,
        ];

        try {
            $result = $this->sendRequest($data["url"], $postBody);
        } catch (\Exception $e) {
            return ['success' => false, "msg" => $e->getMessage()];
        }
        if ($result["code"] == 0) {
            return ["success" => true];
        } else {
            return ['success' => false, $result["results"] ?? ""];
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
        $sign = $this->makesign($data, $thirdChannel->key);

        if ($sign != $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if ((isset($data["outtradeno"]) && $data["outtradeno"] != $transaction->order_number) || (isset($data["outtransferno"]) && $data["outtransferno"] != $transaction->order_number)) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ((isset($data["amount"]) && $data["amount"] != $transaction->amount) || (isset($data["transferamount"]) && $data["transferamount"] != $transaction->amount)) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], [1])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], [4])) {
            return ['fail' => '逾时', "msg" => $data["remark"] ?? ""];
        }

        return ['error' => '未知错误', "msg" => $data["remark"] ?? ""];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "merchant" => $data["merchant"],
        ];

        $postBody["sign"] = $this->makesign($postBody, $data["key"]);

        try {
            $client = new Client();
            $response = $client->request('POST', $data["queryBalanceUrl"], [
                'form_params' => $postBody
            ]);
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $th;
        }
        $row = json_decode($response->getBody(), true);
        if ($row["code"] == 0) {
            $balance = $row["results"]["availableamount"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }

        return 0;
    }

    private function sendRequest($url, $data)
    {
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'form_params' => $data
            ]);
            $row = json_decode($response->getBody(), true);
            Log::debug(self::class, compact('data', 'row'));
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $e;
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        unset($body["sign"]);
        ksort($body);
        // 将null值转换为空字符串或其他表示
        array_walk($body, function (&$value) {
            if (is_null($value)) {
                $value = ''; // 或者使用 'null' 字符串或其他你希望的表示
            }
        });
        $signStr = strtolower(http_build_query($body)) . "&secret=$key";
        return md5($signStr);
    }
}
