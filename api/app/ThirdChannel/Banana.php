<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Client;

class Banana extends ThirdChannel
{
    //Log名称
    public $log_name = 'Banana';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'http://101.36.107.147:17801/deposit';
    public $xiafaUrl = '';
    public $daifuUrl = 'http://101.36.107.147:17801/withdraw/order';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'http://101.36.107.147:17801/withdraw/order/query';
    public $queryBalanceUrl = 'http://101.36.107.147:17801/samount';

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
            'ip' => $data['request']->client_ip ?? '1.1.1.1',
            'orderid' => $data['system_order_number'],
            'mname' => $data['merchant'],
            "mid" => $data["merchant"],
            'money' => strval($data['request']->amount),
            'types' => $data['key2'] ?? '5',
            'returnurl' => $data['callback_url'],
            'remark' => '無',
            'country' => 'china',
            'sname' => $data['request']->real_name ?? '無'
        ];

        $postBody['sign'] = md5($data['key'] . $postBody['mid'] . $postBody['orderid'] . $postBody['money'] . $postBody['returnurl'] . $postBody['remark'] . $postBody['types'] . $postBody['country']);

        try {
            $row = $this->sendRequest($this->depositUrl, $postBody);
            return [
                'success' => true,
                'data' => [
                    'pay_url' => $row['pageaddress'],
                    'receiver_bank_name' => $row['data']['banktypename'] ?? '',
                    'receiver_bank_branch' => $row['data']['cardaddress'] ?? '',
                    'receiver_name' => $row['data']['bankname'] ?? '',
                    'receiver_account' => $row['data']['banknum'] ?? '',
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
        return ['success' => false, 'msg' => '無代付'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false, 'msg' => '無代付'];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if (($data["orderid"] != $transaction->order_number) && ($data["orderid"] != $transaction->system_order_number)) {
            return ['error' => '订单编号不正确'];
        }

        if ($data["money"] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if (in_array($data["status"], [2]) && in_array($transaction->type, [1])) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];

        $postBody = [
            'ip' => $data['request']->client_ip ?? $data['client_ip'] ?? '1.1.1.1',
            "mname" => $data["merchant"],
            'mid' => $data["merchant"],
            'countrys' => 'china',
            'types' => '666',
        ];

        $postBody['sign'] = md5($data['key'] . $postBody['mid'] . $postBody['mname'] . $postBody['types'] . $postBody['ip']);

        try {
            $row = $this->sendRequest($this->queryBalanceUrl, $postBody, debug: false);
            $balance = $row['data']["mbalance"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Throwable $th) {
            return 0;
        }
    }

    private function sendRequest($url, $data, $method = "POST", $debug = true)
    {
        try {
            $client = new Client();
            $response = $client->request($method, $url, [
                "form_params" => $data,
                'timeout' => 60
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if (!isset($row['code'])) {
                throw new \Exception($row['msg']);
            }

            if ($row['code'] != 1) {
                throw new \Exception($row['msg']);
            }


            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = json_decode($response->getBody()->getContents());
                $message = $body['msg'] ?? '';
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
        return strtoupper(md5($signStr));
    }
}
