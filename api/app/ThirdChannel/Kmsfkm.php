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

class Kmsfkm extends ThirdChannel
{
    //Log名称
    public $log_name = 'Kmsfkm';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://admin.kmsfkm.xyz/api/order/create';
    public $xiafaUrl = '';
    public $daifuUrl = '';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = '';
    public $queryBalanceUrl = 'https://admin.kmsfkm.xyz/api/services/app/Api_PayOrder/QueryBalance';

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

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "mid" => $data["merchant"],
            'order_no' => $data['order_number'],
            'client_ip' => $data['request']->client_ip ?? '1.1.1.1',
            'amount' => strval($data['request']->amount),
            "pay_type" => $data["key2"],
            'notify_url' => $data['callback_url'],
        ];

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            $ret = [
                'pay_url' => $row["forward_url"] ?? '',
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
        return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if (($data["order_no"] != $transaction->order_number) && ($data["order_no"] != $transaction->system_order_number)) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data["status"] == "3") {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
//        $this->key = $data["key"];
//
//        $postBody = [
//            "mid" => $data["merchant"],
//        ];
//
//        try {
//            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", false);
//            $balance = $row["balance"];
//            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
//                "balance" => $balance,
//            ]);
//            return $balance;
//        } catch (\Throwable $th) {
//            $message = $th->getMessage();
//            Log::error(self::class, compact('data', 'message'));
//            return 0;
//        }
        return 0;
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
                throw new Exception($row['msg']);
            }

            return $row['data'];
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
        $signStr = urldecode(http_build_query($body) . "&Key=$key");
        return md5($signStr);
    }
}
