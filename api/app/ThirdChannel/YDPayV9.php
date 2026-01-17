<?php

namespace App\ThirdChannel;

use App\Models\ThirdChannel as ThirdChannelModel;
use App\Models\Transaction;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

class YDPayV9 extends ThirdChannel
{
    //Log名称
    public $log_name   = 'YDPayV9';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api3qur2pcg5kdnd.jerqs.xyz/InterfaceV9/CreatePayOrder';
    public $xiafaUrl   = 'https://api3qur2pcg5kdnd.jerqs.xyz/InterfaceV9/CreateWithdrawOrder';
    public $daifuUrl   = 'https://api3qur2pcg5kdnd.jerqs.xyz/InterfaceV9/CreateWithdrawOrder';
    public $queryDepositUrl    = 'https://api3qur2pcg5kdnd.jerqs.xyz/InterfaceV9/QueryPayOrder';
    public $queryDaifuUrl  = 'https://api3qur2pcg5kdnd.jerqs.xyz/InterfaceV9/QueryWithdrawOrder';
    public $queryBalanceUrl = 'https://api3qur2pcg5kdnd.jerqs.xyz/InterfaceV9/GetBalanceAmount';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'SUCCESS';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'kzk'
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $postData = [
            'MerchantId' => $data['merchant'] ?? $this->merchant,
            'MerchantUniqueOrderId' => $data['request']->order_number,
            'Amount'  => $data['request']->amount,
            'NotifyUrl'    => $data['callback_url'],
            'PayTypeId' => $this->channelCodeMap[$this->channelCode],
            "PayTypeIdFormat" => $data["key3"] ?? "URL",
            'Ip' => $data['request']->client_ip ?? $data['client_ip'],
            'Remark' => '',
        ];

        if (isset($data['request']->real_name) && $data['request']->real_name != '') {
            $postData['ClientRealName'] = $data['request']->real_name;
        }
        $postData['Sign'] = $this->makesign($postData, $this->key);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['form_params' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if ($result['Code'] == 0) {
            $ret = [
                'order_number' => $result['MerchantUniqueOrderId'],
                'amount' => $result['RealAmount'],
                'receiver_name' => $result["BankCardRealName"] ?? "",
                'receiver_bank_name' => $result["BankCardBankName"] ?? "",
                'receiver_account' => $result["BankCardNumber"] ?? "",
                'receiver_bank_branch' => $result["BankCardBankBranchName"] ?? "",
                'pay_url'   => $result['Url'] ?? '',
                'created_at' => date('Y-m-d H:i:s'),
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ['success' => false, "msg" => $result["MessageForSystem"] ?? ""];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];

        $postData = [
            'MerchantId' => $data['merchant'] ?? $this->merchant,
            'MerchantUniqueOrderId' => $data['request']->order_number,
            'Amount' => $data['request']->amount,
            'Timestamp' => now()->format('YmdHis'),
            'NotifyUrl' => $data['callback_url'],
            'BankCardBankName' => $data['request']->bank_name,
            'BankCardNumber' => $data['request']->bank_card_number,
            'BankCardRealName' => $data['request']->bank_card_holder_name,
            'WithdrawTypeId' => 0
        ];
        $postData['Sign'] = $this->makesign($postData, $this->key);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], ['form_params' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('result'));

        if ($result['Code'] == 0) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];

        $postData = [
            'MerchantId' => $data['merchant'] ?? $this->merchant,
            'MerchantUniqueOrderId' => $data['request']->order_number,
            'Timestamp' => now()->format('YmdHis')
        ];
        $postData['Sign'] = $this->makesign($postData, $this->key);

        Log::debug(self::class, compact('postData'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], ['form_params' => $postData]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('result'));


        if ($result['Code'] == 0) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        } else {
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['MerchantUniqueOrderId'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //檢查金額
        if (isset($data['Amount']) && $data['Amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        //檢查代收狀態
        if (isset($data['PayOrderStatus']) && in_array($data['PayOrderStatus'], [100])) {
            return ['success' => true];
        }

        //檢查代付狀態
        if (isset($data['Status']) && in_array($data['Status'], [100])) {
            return ['success' => true];
        }

        if (isset($data['PayOrderStatus']) && in_array($data['PayOrderStatus'], [-90])) {
            return ['fail' => '失败'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data["key"];
        $postBody = [
            "MerchantId" => $data["merchant"],
        ];
        $postBody["Sign"] = $this->makesign($postBody, $this->key);

        $client = new Client();
        $response = $client->request('post', $data['queryBalanceUrl'], [
            'form_params' => $postBody
        ]);

        $row = json_decode($response->getBody(), true);

        if ($row["Code"] == "0") {
            $balance = $row["BalanceAmount"];
            ThirdChannelModel::where("id", $data["thirdchannelId"])->update([
                "balance" => $balance,
            ]);
            return $balance;
        }
        return 0;
    }

    public function makesign($data, $key)
    {
        ksort($data);
        $query = http_build_query(
            collect($data)
                ->reject(fn($value) => is_null($value))
                ->all()
        );
        return md5(urldecode($query . $key));
    }
}
