<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;
use Carbon\Carbon;

class XBPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'NSPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://www.xbpay168.com/Pay_Index.html';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://www.xbpay168.com/withdraw/order';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://www.xbpay168.com/withdraw/order/query';
    public $queryBalanceUrl = 'https://www.xbpay168.com/Pay_PayQuery_balance.html';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = 'OK';

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
            "pay_memberid" => $data["merchant"],
            'pay_orderid' => $data['request']->order_number,
            "pay_applydate" => Carbon::now()->format('Y-m-d H:i:s'),
            "pay_bankcode" => $data["key2"],
            'pay_notifyurl' => $data['callback_url'],
            'pay_callbackurl' => "https://www.baidu.com",
            'pay_amount' => strval($data['request']->amount),
            'pay_userid' => strval(random_int(1000000000, 9999999999)),
            'pay_userphone' => strval(136654878445),
            'pay_userip' => '1.1.1.1',
            'pay_productname' => '商品',
            "format" => "json"
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postBody['pay_username'] = $data['request']->real_name;

        } else {
            $postBody['pay_username'] = 'aaa';
        }

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            $detail = $row['detail'];
            $ret = [
                'pay_url' => $row["data"] ?? '',
                'receiver_name' => $detail["name"],
                'receiver_bank_name' => $detail["bankName"],
                'receiver_account' => $detail["cardNo"],
                'receiver_bank_branch' => '',
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

        if ($data["orderid"] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["amount"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["returncode"], ["00"])) {
            return ['success' => true];
        }

        //代付检查状态，失败
        // if (in_array($data["status"], [4])) {
        //     return ['fail' => '逾时'];
        // }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            "pay_memberid" => $data["merchant"],
        ];

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "POST", false);
            $balance = $row["data"];
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

            if ($row['status'] != 'success') {
                throw new Exception($row['msg']);
            }
        } catch (RequestException $e) {
            $response = $e->getResponse();
            $message = json_decode($response->getBody()->getContents());
            $message = $message->message ?? "";
            Log::error(self::class, compact('data', 'message'));
            throw new Exception($message);
        }

        return json_decode($response->getBody(), true);
    }

    public function makesign($body, $key)
    {
        ksort($body);
        $filtered_params = array_filter($body, function ($value) {
            return $value !== '';
        });
        $signStr = urldecode(http_build_query($filtered_params) . "&key=$key");
        return strtoupper(md5($signStr));
    }
}
