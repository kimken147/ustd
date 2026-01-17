<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class Liren extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Liren';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify    = '';
    public $depositUrl   = '';
    public $xiafaUrl   = '';
    public $daifuUrl   = 'https://shapi.lirenpay88.top/v1/dfapi/add';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl  = 'https://shapi.lirenpay88.top/v1/dfapi/query_order';
    public $queryBalanceUrl = 'https://shapi.lirenpay88.top/v1/dfapi/query_balance';

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
        Channel::CODE_BANK_CARD => "BankToBank",
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
       return ['success' => false, 'msg' => '無代收'];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $body = [
            'mchid' => $data['merchant'],
            'out_trade_no' => $data['request']->order_number,
            'money' => number_format($data['request']->amount, 2, ".", ''),
            'notifyurl' => $data['callback_url'],
            'bankname' => $data['request']->bank_name,
            'subbranch' => '無分行',
            'accountname' => $data['request']->bank_card_holder_name,
            'cardnumber' => $data['request']->bank_card_number,
        ];

        try {
            $res = $this->sendRequest($data['url'], $body);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $body = [
            'out_trade_no' => $data['request']->order_number,
            'mchid' => $data['merchant'],
        ];

        try {
            $res = $this->sendRequest($data['queryDaifuUrl'], $body);
            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data["out_trade_no"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data["refCode"] == '3') {
            return ['success' => true];
        }

         //代付检查状态，失败
         if (in_array($data["refCode"], [4, 5])) {
             return ['fail' => $data['refMsg'] ?: '失敗'];
         }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "mchid" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, 'POST', false);
            $balance = $row["balance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            return 0;
        }
    }

    /**
     * @throws GuzzleException
     * @throws Exception
     */
    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $options = $method === "GET"
                ? ["query" => $data]  // GET 方法使用 query 參數
                : ["form_params" => $data];  // POST 方法使用 json 參數

            $response = $client->request($method, $url, $options);

            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['status'] !== 'success') {
                throw new Exception($row['msg']);
            }

            return $row;
        } catch (RequestException $e) {
            $response = $e->getResponse();
            if ($response) {
                $response = json_decode($response->getBody()->getContents());
                $message = $response->message ?? "";
            } else {
                $message = $e->getMessage();
            }

            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = urldecode(http_build_query($body) . "&key=" . $key);
        return strtoupper(md5($signStr));
    }
}
