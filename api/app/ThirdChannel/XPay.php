<?php


namespace App\ThirdChannel;

use App\Model\Channel;
use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class XPay extends ThirdChannel
{
    //Log名称
    public string $log_name = 'XPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://merchant-api.xpmerchantvvip.com/npay/UtInRecordApi/orderGateWay';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://merchant-api.xpmerchantvvip.com/npay/AjaxOpen/saveOutOrder';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://merchant-api.xpmerchantvvip.com/npay/AjaxOpen/queryOrder';
    public $queryBalanceUrl = 'https://merchant-api.xpmerchantvvip.com/npay/AjaxOpen/queryBalance';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = '0000';

    //白名单
    public $whiteIP = ['34.92.161.2'];

    public $channelCodeMap = [
        Channel::CODE_BANK_CARD => 1,
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            'order_id' => $data['order_number'],
            'order_amount' => $data['request']->amount,
            "sys_no" => $data["merchant"],
            'user_id' => Str::random(24),
            'order_ip' => $data['request']->client_ip ?? '1.1.1.1',
            'order_time' => now()->format('Y-m-d H:i:s'),
            'pay_user_name' => $data['request']->real_name ?? '王小明',
            'currency' => 'CNY'
        ];
        ksort($postBody);

        $signString = http_build_query($postBody) . $data['key'];
        $postBody["sign"] = md5($signString);

        $postBody['notify_url'] = $data['callback_url'];

        try {
            $response = $this->sendRequest($data['url'], $postBody);

            if ($response['code'] != 111) {
                throw new \Exception($response['msg']);
            }

            $data = $response['data'];

            return [
                'success' => true,
                'data' => [
                    'pay_url' => $data['send_url'],
                ]
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $body = [
            'data' => json_encode([
                [
                    'user_name' => $data['request']->bank_card_holder_name,
                    'bankcard_no' => $data['request']->bank_card_number,
                    'serial_no' => $data['request']->order_number,
                    'bank_address' => $data['request']->bank_name,
                    'amount' => $data['request']->amount,
                ]
            ], 256 | 64),
            'sys_no' => $data['merchant'],
        ];

        $body['sign'] = md5($body['data'] . $body['sys_no'] . $data['key']);
        $body['notify_url'] = $data['callback_url'];

        try {
            $response = $this->sendRequest($data['url'], $body);

            if ($response['code'] != 200) {
                throw new \Exception($response['msg']);
            }

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    public function queryDaifu($data)
    {
        $body = [
            'sys_no' => $data['merchant'],
        ];
        $body['sign'] = md5($body['sys_no'] . $data['key']);

        try {
            $response = $this->sendRequest($data['queryDaifuUrl'], $body);
            if ($response['code'] != 200) {
                return ['success' => false, 'msg' => $response['msg']];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'msg' => $e->getMessage()];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        //代收檢查狀態
        if ($data['bill_no'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if ($data['order_status'] == 'FAILED' && in_array($transaction->type, [2,4])) {
            return ['fail' => '取消'];
        }

        if ($data['order_status'] == 'SUCCESS') {
            return ['success' => true];
        }

        return ['error' => '未知錯誤'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $body = [
            'sys_no' => $data['merchant'],
        ];

        $body['sign'] = md5(urlencode($body['sys_no'] . $this->key));

        try {
            $response = $this->sendRequest($data['queryBalanceUrl'], $body, debug: false);

            if ($response['code'] != 200) {
                throw new \Exception($response['msg']);
            }

            $balance = $response['data']['balances'][0]['balance'];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function sendRequest($url, $data, $type = "json", $debug = true)
    {
        try {
            $client = new Client();
            $options['form_params'] = $data;
            $response = $client->request('POST', $url, $options);
            $row = json_decode($response->getBody(), true);
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            return $row;
        } catch (RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $responseBody = json_decode($response->getBody()->getContents(), true);
                $message = $responseBody['msg'] ?? $e->getMessage();
            }

            Log::error(self::class, [
                'data' => $data,
                'message' => $message,
            ]);
            throw new \Exception($message);
        }
    }
}
