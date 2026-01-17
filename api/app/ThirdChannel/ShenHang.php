<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use App\Utils\BCMathUtil;

class ShenHang extends ThirdChannel
{
    //Log名称
    public $log_name = 'FeiHou';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://baofuweb7783guanli.newcq2088.com/api/pay/unifiedorder';
    public $xiafaUrl = "";
    public $daifuUrl = 'https://baofuweb7783guanli.newcq2088.com/api/withdraw/unifiedOrder';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://baofuweb7783guanli.newcq2088.com/api/withdraw/query';
    public $queryBalanceUrl = 'https://baofuweb7783guanli.newcq2088.com/api/pay/balance';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = 'SUCCESS';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => "BankToBank",
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "mchid" => $data["merchant"],
            'out_trade_no' => $data['request']->order_number,
            'amount' => $data['request']->amount,
            'channel' => $data['key2'],
            'notify_url' => $data['callback_url'],
            "return_url" => 'https://www.baidu.com/',
            "body" => "123",
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            return [
                'success' => true,
                'data' => [
                    'pay_url' => $row['request_url']
                ]
            ];
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
        return ["success" => false, "msg" => '不支援代付'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data["out_trade_no"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data["amount"]) && $data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ((isset($data["order_status"]) && in_array($data["order_status"], [1]))) {
            return ['success' => true];
        }

        //代付检查状态，失败
//        if ((isset($data["state"]) && in_array($data["state"], [3, 6])) || (isset($data["orderState"]) && in_array($data["orderState"], [3, 6]))) {
//            return ['fail' => '逾时'];
//        }

        return ['error' => "未知错误"];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "mchid" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", $debug = false);
            $balance = $row["balance"];
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
        $data["sign"] = $this->makesign($data, $this->key);
        try {
            $client = new Client();
            $response = $client->request($method, $url, [
                "form_params" => $data,
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['code'] != '0') {
                throw new \Exception($row['msg']);
            }

            return $row['data'];
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBody = json_decode($response->getBody()->getContents());
                $message = $responseBody['msg'] ?? $e->getMessage();
            }

            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = urldecode(http_build_query($body) . "&key=$key");
        return (md5($signStr));
    }
}
