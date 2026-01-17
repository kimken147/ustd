<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use App\Model\Transaction;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;

class K2Pay extends ThirdChannel
{
    //Log名称
    public $log_name   = 'K2Pay';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付
    public $baseUrl = "https://k2.lazabong.com";

    //回调地址
    public $notify    = '';
    public $depositUrl  = "https://k2.lazabong.com/api/deposit-url";
    public $xiafaUrl   =  "https://k2.lazabong.com/payment";
    public $daifuUrl   = 'https://k2.lazabong.com/api/withdrawal';
    public $queryDepositUrl    = 'https://k2.lazabong.com/transaction';
    public $queryDaifuUrl  = 'https://k2.lazabong.com/api/withdrawal/info';
    public $queryBalanceUrl = 'https://k2.lazabong.com/api/balance';

    //默认商户号
    public $merchant    = '';

    //默认密钥
    public $key          = '';
    public $key2         = '';

    //回传字串
    public $success = "success";

    //白名单
    public $whiteIP = ['13.209.119.152'];

    public $channelCodeMap = [
        'BANK_CARD' => 'bankcard'
    ];

    public function __construct()
    {
        $this->success = new JsonResponse([
            "code" => 200,
            "message" => "success"
        ]);
    }

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];
        $request = $data["request"];
        $post = [
            'platformId' => $data["merchant"],
            "amount" => $request->amount,
            "playerName" => "abc123",
            "depositMethod" => 8,
            "entryType" => "0",
            "clientType" => "0",
            'callbackUrl' => $data['callback_url'],
            'proposalId' => $request->order_number,
        ];
        $post["sign"] = $this->makesign($post);
        $client = new Client();
        try {
            $res = $client->post($this->depositUrl, [
                "headers" => [
                    "platform" => $data["merchant"],
                ],
                "json" => $post
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('$post', 'message'));
            return ["success" => false];
        }

        $row = json_decode($res->getBody(), true);
        Log::debug(self::class, compact('post', 'row'));

        return [
            "success" => true,
            "data" => [
                "amount" => $data["request"]->amount,
                "order_number" => $data["request"]->order_number,
                "pay_url" => $row["reqUrl"]
            ]
        ];
    }

    public function queryDeposit($data)
    {
        return ['success' => true, 'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];
        $request = $data["request"];
        $client = new Client();

        $post_data = [
            'platformId' => $data["merchant"],
            "proposalId" => $request->order_number,
            'amount' =>  $request->amount,
            "accountNo" => $request->bank_card_number,
            'callbackUrl' => $data['callback_url'],
        ];

        $post_data['sign'] = $this->makesign($post_data);
        try {
            $response = $client->post($this->daifuUrl, [
                "headers" => [
                    "platform" => $data["merchant"],
                ],
                "json" => $post_data
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('post_data', 'message'));
            return [
                "success" => false
            ];
        }
        $resData = json_decode($response->getBody(), true);
        Log::debug(self::class, compact('post_data', 'resData'));
        if ($resData["code"] == 200) {
            return ["success" => true];
        }
        return ["success" => false];
    }

    public function queryDaifu($data)
    {
        $this->key = $data['key'];
        $merchantId = $data["merchant"];
        $postData = [
            "proposalId" => $data["request"]->order_number,
        ];
        $postData["sign"] = $this->makesign($postData);
        $client = new Client();
        try {
            $response = $client->post($this->queryDaifuUrl, [
                "json" => $postData,
                "headers" => [
                    "platform" => $merchantId
                ]
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('postData', 'message'));
            return [
                "success" => false
            ];
        }
        $return_data = json_decode($response->getBody(), true);
        Log::debug(self::class, compact('orderNumber', 'return_data'));
        $sign = $this->makesign($return_data["content"]);
        if ($sign != $return_data["sign"]) {
            return ["success" => false];
        }
        $status = $return_data["orderStatus"];
        if ($status == 1) {
            return ["success" => true, "status" => Transaction::STATUS_SUCCESS];
        } else if ($status == 2) {
            return ["success" => false, "status" => Transaction::STATUS_FAILED];
        } else {
            return ["success" => true, "status" => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,订单资料)   */
    public function callback(Request $request, Transaction $transaction, ThirdChannelModel $thirdChannel)
    {
        $this->key = $thirdChannel->key;
        $data = $request->all();
        $content = $data["content"];
        $sign = $this->makesign($content);

        if ($sign !== $data["sign"]) {
            return ["error" => "签名错误"];
        }

        if ($data['proposalId'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        //代收检查金额
        if (isset($content['amount']) && $content['amount'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        //代收检查状态
        if (isset($content["status"])) {
            $status = $content["status"];
            if ($status == "PENDING") {
                return ['success' => false, "resBody" => [
                    "code" => 200,
                    "message" => "success"
                ]];
            }

            if ($status == "SUCCESS") {
                return ["success" => true, "resBody" => [
                    "code" => 200,
                    "message" => "success"
                ]];
            }

            if ($status == "CANCEL") {
                return ['fail' => '支付失败', "resBody" => [
                    "error" => 400,
                    "message" => "支付失败"
                ]];
            }

            return ['error' => '未知错误', "resBody" => [
                "error" => 400,
                "message" => "支付失败"
            ]];
        }
        //代付檢查狀態
        if (isset($content["orderStatus"])) {
            $orderStatus = $content["orderStatus"];

            if ($orderStatus == 1) {
                return ["success" => true, "resBody" => [
                    "code" => 200,
                    "message" => "success"
                ]];
            } else if ($orderStatus == 2) {
                return ['fail' => '支付失败', "resBody" => [
                    "error" => 400,
                    "message" => "支付失败"
                ]];
            } else {
                return ["success" => false, "resBody" => [
                    "code" => 200,
                    "message" => "success"
                ]];
            }
            return ['error' => '未知错误', "resBody" => [
                "error" => 400,
                "message" => "支付失败"
            ]];
        }
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $postData = [
            "timestamp" => time()
        ];
        $postData["sign"] = $this->makesign($postData);
        $client = new Client();
        try {
            $response = $client->post($this->queryBalanceUrl, [
                "json" => $postData,
                "headers" => [
                    "platform" => $data["merchant"]
                ]
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('message'));
            return 0;
        }
        $return_data = json_decode($response->getBody(), true);

        Log::debug(self::class . "/queryBalance", compact("return_data"));

        if (isset($return_data["error"]) && $return_data["error"] == 400) {
            return 0;
        }
        if (isset($return_data["balance"])) {
            $balance = $return_data['balance'];
            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);
            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data)
    {
        ksort($data);
        $strSign = urldecode(http_build_query($data));
        $sign = hash_hmac("sha256", $strSign, $this->key);
        return $sign;
    }
}
