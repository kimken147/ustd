<?php


namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\ThirdChannel as ThirdChannelModel;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class IPay extends ThirdChannel
{
    //Log名称
    public string $log_name = 'IPay';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://pay.ipaynow.cn';
    public $xiafaUrl = '';
    public $daifuUrl = '';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = '';
    public $queryBalanceUrl = 'https://pay.ipaynow.cn/api/biz/query_merchant_balance';

    //默认商户号
    public $merchant = '';

    //默认密钥
    public $key = '';
    public $key2 = '';

    //回传字串
    public $success = 'success=Y';

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
            'funcode' => 'WP001',
            'version' => '1.0.2',
            'appId' => $data['merchant'],
            'mhtOrderNo' => $data['order_number'],
            'mhtOrderName' => '支付',
            'mhtOrderType' => '01',
            'mhtCurrencyType' => '156',
            'mhtOrderAmt' => intval($this->bcMathUtil->mul($data['request']->amount, 100, 0)),
            'mhtOrderDetail' => '详细',
            'mhtOrderStartTime' => now()->format('YmdHis'),
            'notifyUrl' => $data['callback_url'],
            'frontNotifyUrl' => $data['callback_url'],
            'mhtCharset' => 'UTF-8',
            'deviceType' => '0601',
            'payChannelType' => $data['key2'],
            'outputType' => $data['key2'] == 12 ? 2 : 1,
            'mhtSignType' => 'MD5',
        ];

        try {
            $resData = $this->sendRequest($data["url"], $postBody);
            $ret = [
                'pay_url' => $resData["tn"] ?? '',
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
        return ["success" => false, "msg" => '無代付'];
    }

    public function queryDaifu($data)
    {
        return ["success" => false, "msg" => '無代付'];
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        $sign = $this->makeSign($data, $thirdChannel->key);
        if ($sign != $data['signature']) {
            return ['error' => '簽名錯誤'];
        }

        if (
            $data["mhtOrderNo"] != $transaction->order_number &&
            $data["mhtOrderNo"] != $transaction->system_order_number
        ) {
            return ['error' => '支付订单编号不正确'];
        }


        //代收检查金额
        if ($data["mhtOrderAmt"] != $this->bcMathUtil->mul($transaction->amount, 100, 0)) {
            return ['error' => '金额不正确'];
        }

        if ($data['transStatus'] == 'A001') {
            return ['success' => true];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        return 0;
    }

    private function sendRequest($url, $data, $type = "json", $debug = true)
    {
        try {
            $client = new Client();
            $data['mhtSignature'] = $this->makesign($data, $this->key);
            $response = $client->request('POST', $url, [
                "form_params" => $data,
            ]);
            $row = $this->decrypt($response->getBody()->getContents());
            if ($debug) {
                Log::debug(self::class, compact('data', 'row'));
            }

            if ($row['responseCode'] != "A001") {
                throw new \Exception($row['responseMsg'] ?? '');
            }

            return $row;
        } catch (\Exception $e) {
            $msg = $e->getMessage();
            if ($e instanceof RequestException) {
                if ($e->hasResponse()) {
                    $response = json_decode($e->getResponse()->getBody()->getContents(), true);
                    $msg = $response["msg"] ?? '未知錯誤';
                }
            }
            Log::error(self::class, [
                'data' => $data,
                'msg' => $msg,
            ]);
            throw $e;
        }
    }

    public function makesign($body, $key)
    {
        unset($body['signature']);
        ksort($body);
        return md5(urldecode(http_build_query($body) . '&' . md5($key)));
    }

    public function decrypt($body)
    {
        $result = [];
        parse_str($body, $result);
        return $result;
    }
}
