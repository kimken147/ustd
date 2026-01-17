<?php

namespace App\ThirdChannel;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Test extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Test';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://www.onetais.com/Pay_Index.html';
    public $xiafaUrl   = 'https://www.onetais.com/Payment_Dfpay_add.html';
    public $daifuUrl   = 'https://www.onetais.com/Payment_Dfpay_add.html';
    public $queryDepositUrl = 'https://www.onetais.com/Pay_Trade_query.html';
    public $queryDaifuUrl  = 'https://www.onetais.com/Payment_Dfpay_query.html';
    public $queryBalanceUrl = 'https://www.onetais.com/Payment_Dfpay_balance.html';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'OK';

    //白名单
    public $whiteIP = ['169.129.221.204'];

    public $channelCodeMap = [
        'BANK_CARD' => 1
    ];
    /*   代收   */
    public function sendDeposit($data)
    {
        return ['success' => true, 'data' => [
            'pay_url'   => 'test',
        ]];
    }

    public function queryDeposit($data)
    {
        return ['success' => true,'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        return ['success' => true, 'msg' => '測試用'];
    }

    public function queryDaifu($data)
    {
        return ['success' => true, 'msg' => '測試用'];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        return ['success' => true, 'msg' => '測試用'];
    }

    public function queryBalance($data)
    {
        if ($data['key'] == 1) {
            return 0;
        }

        return 99999999;
    }

    public function makesign($data){
        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            $signstr = $signstr . $k . "=" . $v . "&";
        }
        return strtoupper(md5($signstr . "key=" . $this->key));
    }
}
