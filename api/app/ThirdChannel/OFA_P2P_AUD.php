<?php

namespace App\ThirdChannel;

use App\Models\Channel;
use App\Models\Transaction;
use App\Models\ThirdChannel as ThirdChannelModel;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Faker\Factory;

class OFA_P2P_AUD extends ThirdChannel
{
    //Log名称
    public $log_name = 'OFA_P2P_AUD';
    public $type = 1; //1:代收付 2:纯代收 3:纯代付－

    //回调地址
    public $notify = '';
    public $depositUrl = 'https://p2p.jzc899.com/service/pay.aspx';
    public $xiafaUrl = '';
    public $daifuUrl = 'https://p2p.jzc899.com/service/withdraw.aspx';
    public $queryDepositUrl = '';
    public $queryDaifuUrl = 'https://p2p.jzc899.com/service/query_withdraw.aspx';
    public $queryBalanceUrl = 'https://p2p.jzc899.com/service/query_balance.aspx';

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
        Channel::CODE_BANK_CARD => 1,
    ];

    public $bankMap = [];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $postBody = [
            "scode" => $data["merchant"],
            'orderid' => $data['request']->order_number,
            'amount' => number_format($data['request']->amount, 2, '.', ''),
            'currcode' => 'CNY',
            'paytype' => 'A1',
            'noticeurl' => $data['callback_url'],
        ];

        $postBody['sign'] = md5(implode('|', $postBody) . ":{$data['key']}");
        $postBody['productname'] = 'Service';
        $postBody['redirectpage'] = '0';
        $postBody['userid'] = random_int(10000, 99999);
        $postBody['payername'] = $data['request']->real_name ?? '王小明';
        $postBody['lang'] = 'en_aus';
        $postBody['returnurl'] = $data['request']->return_url ?? 'https://www.baidu.com';

        try {
            $row = $this->sendRequest($data["url"], $postBody);
            $data = $row['data'];
            $ret = [
                'pay_url' => $data["url"] ?? '',
                "receiver_name" => $data["acctname"] ?? "",
                'receiver_bank_name' => $data["bkname"] ?? "",
                'receiver_account' => $data["bkno"] ?? "",
                'receiver_bank_branch' => $data["branch"] ?? "",
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
        $body = [
            'scode' => $data['merchant'],
            'orderid' => $data['request']->order_number,
            'money' => number_format($data['request']->amount, 2, '.', ''),
            'dftype' => 'A1D',
            'accountno' => $data['request']->bank_card_number,
        ];

        $body['sign'] = md5(implode('|', $body) . ":{$data['key']}");
        $body['accountname'] = $data['request']->bank_card_holder_name;
        $body['notifyurl'] = $data['callback_url'];
        $body['bankname'] = $data['request']->bank_name;

        try {
            $row = $this->sendRequest($data["url"], $body);
            return ['success' => true];
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }
    }

    public function queryDaifu($data)
    {
        $body = [
            'scode' => $data['merchant'],
            'orderid' => $data['request']->order_number,
        ];
        $body['sign'] = md5(implode('|', $body) . ":{$data['key']}");

        try {
            $this->sendRequest($data["queryDaifuUrl"], $body);
            return ['success' => true];
        } catch (\Throwable $th) {
            return ["success" => false, "msg" => $th->getMessage()];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback($request, $transaction, ThirdChannelModel $thirdChannel)
    {
        $data = $request->all();

        if ($data['orderid'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if ((isset($data["amount"]) && $data["amount"] != $transaction->amount)) {
            return ['error' => '金额不正确'];
        }

        //代收检查状态
        if ($data['respcode'] == '00') {
            return ['success' => true];
        }
        if ($data['respcode'] == '99') {
            return ['fail' => '失敗'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "scode" => $data["merchant"],
        ];

        $postBody['sign'] = md5($postBody['scode'] . ':' . $data['key']);

        try {
            $row = $this->sendRequest($data["queryBalanceUrl"], $postBody, "json", false);
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
    private function sendRequest($url, $data, $type = "json", $debug = true)
    {
        $client = new Client();
        $response = $client->request('POST', $url, [
            "form_params" => $data
        ]);
        $row = json_decode($response->getBody(), true);
        if ($debug) {
            Log::debug(self::class, compact('data', 'row'));
        }

        if (isset($row['errcode']) && $row['errcode'] != '00') {
            throw new Exception($row['respmsg'] ?? '');
        } else if (isset($row['status']) && $row['status'] != '1') {
            throw new Exception($row['respmsg'] ?? '');
        } elseif (isset($row['respcode']) && $row['respcode'] != '00') {
            throw new Exception($row['respmsg'] ?? '');
        }


        return $row;
    }

    public function makesign($body, $key)
    {
        ksort($body);
        unset($body["sign"]);
        $signStr = urldecode(http_build_query($body)) . "&$key";
        return (md5($signStr));
    }
}
