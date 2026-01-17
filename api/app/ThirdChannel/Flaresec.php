<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class Flaresec extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Flaresec';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = '';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://dawn.flaresec.com/order/create/';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://dawn.flaresec.com//order/query/';
    public $queryBalanceUrl = 'https://dawn.flaresec.com/order/money/';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'OK';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "BankToBank",
    ];

    public $bankMap = [];
    /*   代收   */
    public function sendDeposit($data)
    {
        return ["success" => false, 'msg' => '不支援代收'];
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
            "pay_memberid" => $data["merchant"],
            'pay_orderid' => $data['request']->order_number,
            'pay_applydate' => time(),
            'pay_bankcode' => $data['key2'] ?: 2,
            'pay_amount' => $this->bcMathUtil->mul($data['request']->amount, 100, 0),
            'pay_notifyurl' => $data['callback_url'],
            "pay_name" => $data['request']->bank_card_holder_name,
            'pay_card' => $data['request']->bank_card_number,
            'pay_bankname' => $data["request"]->bank_name,
        ];

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
            "memberid" => $data["merchant"],
            'orderid' => $data['request']->order_number,
        ];

        $postBody['sign'] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->post($data["queryBalanceUrl"], [
                'form_params' => $postBody
            ]);

            $row = json_decode($response->getBody(), true);

            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data["orderid"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["money"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }
        //代付检查状态
        if (in_array($data["status"], [1]) && in_array($transaction->type, [4])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (in_array($data["status"], [5])) {
            return ['fail' => '逾时', 'msg' => $data['msg'] ?? ''];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "memberid" => $data["merchant"],
        ];

        $postBody['sign'] = $this->makesign($postBody, $this->key);

        try {
            $client = new Client();
            $response = $client->post($data["queryBalanceUrl"], [
                'form_params' => $postBody
            ]);

            $row = json_decode($response->getBody(), true);

            if ($row['status'] != 1) {
                return 0;
            }
            $balance = $row["money"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            $message = $th->getMessage();
            Log::error(self::class, compact('postBody', 'message'));
            return 0;
        }
    }

    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $data["pay_md5sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $response = $client->request($method, $url, [
                "form_params" => $data,
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['status'] != 1) {
                throw new Exception($row['msg'], 1);
            }

            return $row;
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message = json_decode($response->getBody()->getContents());
            $message = $message->message ?? "";
            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["pay_md5sign"]);
        $signStr = urldecode(http_build_query($body) . "&key=$key");
        return strtoupper(md5($signStr));
    }
}
