<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use App\Models\ThirdChannel as ThirdChannelModel;
use GuzzleHttp\Client;

class KKPay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'KKPay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://kkpay.galaxy5588.com/api/transfer';
    public $xiafaUrl   = 'https://kkpay.galaxy5588.com/payment';
    public $daifuUrl   = 'https://kkpay.galaxy5588.com/api/daifu';
    public $queryDepositUrl    = 'https://kkpay.galaxy5588.com/transaction';
    public $queryDaifuUrl  = 'https://kkpay.galaxy5588.com/api/query';
    public $queryBalanceUrl = 'https://kkpay.galaxy5588.com/api/me';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = 'ok';

    //白名单
    public $whiteIP = ['13.209.119.152'];

    public $channelCodeMap = [
        'GCASH' => 'gcash',
        "GCash" => "gcash",
        "BPI / BPI Family Savings Bank" => "bpi",
        "BDO Unibank, Inc." => "bpi",
        "Metropolitan Bank and Trust Co." => "mbt",
        "LANDBANK / OFBank" => "LBOB",
        "Security Bank Corporation" => "SBC",
        "Union Bank of the Philippines" => "UBP",
        "Philippine National Bank (PNB)" => "PNB",
        "China Banking Corporation" => "CBC",
        "East West Banking Corporation" => "EWBC",
        "RCBC/DiskarTech" => "RCBC",
        "United Coconut Planters Bank (UCPB)" => "UCPB",
        "Philippine Savings Bank" => "PSB",
        "Asia United Bank Corporation" => "AUB",
        "Philippine Bank of Communications" => "PBC",
        "Development Bank of the Philippines" => "DBP",
        "AllBank (A Thrift Bank), Inc." => "AB",
        "Bangko Mabuhay" => "BM",
        "Bank of Commerce" => "BC",
        "BanKo, A Subsidiary of BPI" => "BK",
        "BDO Network Bank" => "BNB",
        "Camalig Bank" => "CB",
        "CARD Bank Inc." => "CARD Bank",
        "Cebuana Lhuillier Bank / Cebuana Xpress" => "CLB",
        "China Bank Savings, Inc." => "CBS",
        "DCPay / COINS.PH" => "Coins",
        "CTBC Bank (Philippines) Corporation" => "CTBC",
        "Dumaguete City Development Bank" => "DCDB",
        "Dungganon Bank (A Microfinance Rural Bank), Inc." => "DB",
        "Equicom Savings Bank, Inc." => "ESB",
        "GrabPay" => "GP",
        "ISLA Bank (A Thrift Bank), Inc." => "ISLA",
        "Zybi Tech Inc. / JuanCash" => "JC",
        "East West Rural Bank / Komo" => "Komo",
        "Legazpi Savings Bank" => "LSB",
        "Malayan Bank Savings and Mortgage Bank, Inc." => "MBS",
        "Maybank Philippines, Inc." => "MBP",
        "Mindanao Consolidated CoopBank" => "MCCB",
        "Netbank" => "NB",
        "OmniPay, Inc." => "OP",
        "Partner Rural Bank (Cotabato), Inc." => "PRB",
        "PayMaya / Maya Wallet" => "PMP",
        "Philippine Business Bank, Inc., A Savings Bank" => "PBB",
        "Philippine Trust Company" => "PTC",
        "Producers Bank" => "PDB",
        "Queenbank" => "QB",
        "Quezon Capital Rural Bank" => "QCRB",
        "Robinsons Bank Corporation" => "RBB",
        "Seabank" => "SB",
        "ShopeePay" => "SP",
        "Standard Chartered Bank" => "SCB",
        "Starpay" => "STP",
        "Sterling Bank of Asia, Inc (A Savings Bank)" => "SLB",
        "Sun Savings Bank, Inc." => "SSB",
        "TayoCash" => "TC",
        "UCPB Savings Bank" => "USB",
        "USSC Money Services" => "USSC",
        "Veterans Bank" => "VB",
        "Wealth Development Bank" => "WDB"
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $post = [
            "merchant" => $data["merchant"],
            "payment_type" => 3,
            'amount' => $data['request']->amount,
            'order_id' => $data['request']->order_number,
            "bank_code" => $this->channelCodeMap[$data["request"]->channel_code],
            'callback_url' => $data['callback_url'],
            "return_url" => isset($data["request"]->return_url) ? $data["request"]->return_url : "https://google.com"
        ];
        $post["sign"] = $this->makesign($post);
        $client = new Client();
        try {
            $res = $client->post($this->depositUrl, [
                "json" => $post
            ]);
        } catch (\Exception $e) {
            Log::error($e->getMessage(), $post);
            return ["success" => false];
        }

        $row = json_decode($res->getBody(), true);
        Log::debug(self::class, compact('post', 'row'));

        $status = $row["status"];
        if ($status == 1) {
            $ret = [
                "order_number" => $row["order_id"],
                "amount" => $row["amount"],
                "pay_url" => $row["redirect_url"]
            ];
            return ['success' => true, "data" => $ret];
        }
        return ["success" => false];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $client = new Client();

        $post_data = [
            "merchant" => $data["merchant"],
            "total_amount" => $data['request']->amount,
            'callback_url' => $data['callback_url'],
            'order_id' => $data['request']->order_number,
            'amount' => $data['request']->amount,
            'bank' => $this->channelCodeMap[$data["request"]->bank_name] ?? "gcash",
            'bank_card_name' => $data['request']->bank_card_number,
            'bank_card_account' => $data['request']->bank_card_number,
            "bank_card_remark" => $data['request']->bank_card_number
        ];

        $post_data['sign'] = $this->makesign($post_data);
        $response = $client->post($this->daifuUrl, [
            "json" => $post_data
        ]);
        $return_data = json_decode($response->getBody(), true);
        Log::debug(self::class, compact('post_data', 'return_data'));
        $status = $return_data["status"];
        if ($status == 1) {
            return ["success" => true];
        }
        return ["success" => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $client = new Client();
        $post = [
            "merchant" => $data["merchant"],
            "order_id" => $data["request"]->order_number,
        ];
        $post["sign"] = $this->makesign($post);
        $response = $client->post($this->queryDaifuUrl, [
            "json" => $post,
        ]);
        $return_data = json_decode($response->getBody(), true);
        $status = $return_data["status"];
        Log::debug(self::class, compact('orderNumber', 'return_data'));

        return $this->getState($status);
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback(Request $request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $this->key = $thirdChannel->key;
        $data = $request->all();
        $signData = $request->except(["sign"]);
        $sign = $this->makesign($signData);

        if ($sign !== $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if ($data['order_id'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        //代收、代付检查状态
        if (isset($data['status']) && $data["status"] == 5) {
            return ['success' => true];
        }

        //代付检查状态，失败
        if (isset($data['status']) && in_array($data["status"], [3])) {
            return ['fail' => '支付失败'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {

        $this->key = $data['key'];
        $logName = self::class . __METHOD__;
        $client = new Client();
        $postData = [
            "merchant" => $data["merchant"]
        ];
        $postData["sign"] = $this->makesign($postData);
        try {
            $response = $client->post($this->queryBalanceUrl, [
                "json" => $postData
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error($logName, compact('message'));
            return [
                "success" => false
            ];
        }
        $return_data = json_decode($response->getBody(), true);

        Log::debug($logName, compact("return_data"));

        $sign = $return_data["sign"];
        unset($return_data["sign"]);
        if ($sign != $this->makesign($return_data)) {
            return 0;
        }
        $balance = $return_data['balance'];
        ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);
        return $balance;
    }

    public function makesign($data)
    {
        ksort($data);
        $data = urldecode(http_build_query($data));
        $strSign = "$data&key=$this->key";
        $sign = md5($strSign);
        return $sign;
    }

    private function getState($status)
    {
        if ($status == 5) {
            return ["success" => true, "status" => Transaction::STATUS_SUCCESS];
        } else if (in_array($status, [1, 2, 6])) {
            return ["success" => true, "status" => Transaction::STATUS_PAYING];
        } else {
            return ["success" => false, "status" => Transaction::STATUS_PAYING];
        }
    }
}
