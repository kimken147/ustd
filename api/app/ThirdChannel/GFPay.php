<?php

namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\Transaction;
use App\Model\ThirdChannel as ThirdChannelModel;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class GFPay extends ThirdChannel
{
    //Log名称
    public $log_name = 'GFPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://api.xinzf.cc/merchant/recharge/save-order';
    public $xiafaUrl = '';
    public $daifuUrl = '';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = '';
    public $queryBalanceUrl = 'https://api.xinzf.cc/merchant/recharge/query-balance';

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
        Channel::CODE_QR_ALIPAY => '14',
    ];

    public $bankMap = [];


    private function getHeaders(string $key)
    {
        return [
            'Authorization' => 'api-key ' . $key
        ];
    }

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $body = [
            "merchantNo" => $data["merchant"],
            'customerNo' => $data['request']->order_number,
            'userName' => random_int(100000, 999999),
            'amount' => $data['request']->amount,
            'returnType' => 2,
            'stype' => $data['key2'] ?? $this->channelCodeMap[$this->channelCode],
            'timestamp' => time() * 1000,
            'notifyUrl' => $data['callback_url'],
            'payIp' => $data['request']->client_ip ?? $data['client_ip'] ?? '1.1.1.1',
        ];

        $body['sign'] = $this->makeSign($body, $this->key);
        $body['depositName'] = $data['request']->real_name ?? '王小明';
        $body['realName'] = $data['request']->real_name ?? '王小明';


        try {
            $row = $this->sendRequest($data["url"], $body);
            $ret = [
                'pay_url' => $row['payUrl'] ?? '',
                'receiver_name' => $row['accountName'] ?? '',
                'receiver_bank_name' => $row["bankName"] ?? '',
                'receiver_account' => $row['bankCardNo'] ?? '',
                'receiver_bank_branch' => $row['bankBranch'] ?? '',
            ];
            return ['success' => true, 'data' => $ret];
        } catch (\Throwable $th) {
            return ["success" => false, 'msg' => $th->getMessage()];
        }


    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        return ['success' => false, 'msg' => '不支持此銀行代付'];
    }

    public function queryDaifu($data)
    {
        return ['success' => false, 'msg' => '不支持此銀行代付'];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();
        // if (!$this->verifySign($data, $thirdChannel->key2)) {
        //     return ["error" => "签名错误"];
        // }

        if ($data['customerNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ($data['amount'] != $transaction->amount) {
            return ['error' => '代收金额不正确'];
        }

        //代收检查状态
        if ($data['status'] == 0) {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $body = [
            "merchantNo" => $data["merchant"],
            'timestamp' => time() * 1000,
        ];

        $body['sign'] = $this->makeSign($body, $this->key);

        try {
            $response = $this->sendRequest($data["queryBalanceUrl"], $body, false);
            $balance = $response["merchantBalance"];
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
    private function sendRequest($url, array $data, $debug = true)
    {
        try {
            $client = new Client();
            $response = $client->request('POST', $url, [
                'json' => $data,
            ]);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['code'] != 0) {
                throw new Exception($row['msg'] ?? "未知錯誤");
            }

            return $row['data'];
        } catch (\Exception $e) {
            if ($debug) {
                Log::debug(self::class, compact('data', 'e'));
            }
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'message'));
            throw $e;
        }
    }


    public function makesign(array $body, string $key)
    {
        ksort($body);
        $signStr = urldecode(http_build_query($body) . "&key=$key");

        return strtoupper(md5($signStr));
    }
}
