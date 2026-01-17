<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;

class XiangYun extends ThirdChannel
{
    //Log名称
    public $log_name   = 'XiangYun';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'https://api.topmav.com/pay/unifiedorder';
    public $xiafaUrl   = 'https://api.topmav.com/withdraw/unifiedorder';
    public $daifuUrl   = 'https://api.topmav.com/withdraw/unifiedorder';
    public $queryDepositUrl = 'https://api.topmav.com/pay/queryorder';
    public $queryDaifuUrl  = 'https://api.topmav.com/withdraw/queryorder';
    public $queryBalanceUrl = 'https://api.topmav.com/withdraw/balance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'success';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
        'BANK_CARD' => 'bankcard'
    ];

    public $bankMap = [
        '工商银行' => 'ICBC',
        '建设银行' => 'CCB',
        '农业银行' => 'ABC',
        '中国银行' => 'BOC',
        '交通银行' => 'COMM',
        '招商银行' => 'CMB',
        '民生银行' => 'CMBC',
        '光大银行' => 'CEB',
        '北京银行' => 'BJBANK',
        '上海银行' => 'SHBANK',
        '兴业银行' => 'CIB',
        '邮政银行' => 'PSBC',
        '平安银行' => 'SPABANK',
        '浦发银行' => 'SPDB',
        '广发银行' => 'GDB',
        '农村信用社' => 'GDRCC',
        '华夏银行' => 'HXBANK',
        '泉州银行' => 'BOQZ',
        '深圳农村商业银行' => 'SRCB',
        '云南农村信用社' => 'YNRCC',
        '东莞银行' => 'BOD',
        '东莞农村商业银行' => 'DRCBCL',
        '中信银行' => 'CITIC',
        '福建农村信用社' => 'FJNX',
        '北京农村商业银行' => 'BJRCB',
        '湖北农村信用社' => 'HURCB',
        '湖北银行' => 'HBC',
        '江苏银行' => 'JSBANK',
        '九江银行' => 'JJBANK',
        '顺德农商银行' => 'SDEB',
        '广州农商银行' => 'GRCB',
        '湖南农村信用社' => 'HNRCC',
        '长安银行' => 'CABANK',
        '西安银行' => 'XABANK',
        '浙商银行' => 'CZBANK',
        '陕西省农村信用社联合社' => 'SXRCCU',
        '福建海峡银行' => 'FJHXBC',
        '南京银行' => 'NJCB',
        '恒丰银行' => 'EGBANK',
        '长沙银行' => 'CSCB',
        '重庆农村商业银行' => 'CRCBANK',
        '浙江网商银行' => 'ANTBANK',
        '广东省农村信用社联合社' => 'GDRCC',
        '广西农村信用社联合社' => 'GXRCU',
        '贵州省农村信用社联合社' => 'GZRCU',
        '海南省农村信用社联合社' => 'BOHN',
        '吉林省农村信用社联合社' => 'JLRCU',
        '江苏省农村信用社联合社' => 'JSRCU',
        '江西省农村信用社联合社' => 'JXRCU',
        '山东省农村信用社联合社' => 'SDRCU',
        '重庆三峡银行' => 'CCQTGB',
        '成都银行' => 'CDCB',
        '浙江省农村信用社联合社' => 'ZJNX',
        '齐鲁银行' => 'QLBANK',
        '河北农村信用社联合社' => 'HBRCU',
        '中原银行' => 'ZYB',
        '晋商银行' => 'JSB',
        '河南农商银行' => 'HNRCU',
        '广州银行' => 'GCB',
        '龙江银行' => 'DAQINGB',
        '富滇银行' => 'FDB',
        '徽商银行' => 'HSBANK',
        '贵阳银行' => 'GYCB',
        '河南农村信用社' => 'HNRCU',
        '江西银行' => 'NCB',
        '四川农信银行' => 'SCRCU',
        '贵州银行' => 'ZYCBANK',
        '苏州银行' => 'BOSZ',
        '河北银行' => 'BHB',
        '农商银行' => 'SDRCU',
        '哈尔滨银行' => 'HRBANK',
        '四川省农村信用社' => 'SCRCU',
        '上海农商银行' => 'SHRCB',
        '泰隆银行' => 'ZJTLCB',
        '郑州银行' => 'ZZBANK',
        '广西北部湾银行' => 'BGB',
        '桂林银行' => 'GLBANK',
        '广东南粤银行' => 'NYBANK',
        '青岛银行' => 'QDCCB',
        '浙江稠州商业银行' => 'CZCB',
        '杭州银行' => 'HZCB',
        '天府银行' => 'CGNB',
        '湖北农商银行' => 'HURCB',
        '宁波银行' => 'NBBANK',
        '日照银行' => 'RZB',
        '山西农村信用社' => 'SXRCU',
        '杭州农村信用社' => 'ZJNX',
        '邢台银行' => 'XTB',
        '江苏农村商业银行' => 'JSRCU',
        '济宁银行' => 'JNBANK',
        '中银富登村镇银行' => 'BOCFCB',
        '江西农商银行' => 'JXRCU',
        '华润银行' => 'RBOZ',
        '吉林银行' => 'JLBANK',
        '江苏省农村商业银行' => 'JSRCU',
        '洛阳银行' => 'BOL',
        '商业银行' => 'WHCCB',
        '盛京银行' => 'SJBANK',
        '天津银行' => 'TCCB',
        '渤海银行' => 'BOHAIB',
        '邯郸银行' => 'HDBANK',
        '武汉农村商业银行' => 'WHRCB',
        '蒙商银行' => 'BSB',
        '常熟农村商业银行行' => 'CSRCB',
        '山东农商' => 'SDRCU',
        '农村信用联社' => 'LNRCC',
        '营口银行' => 'BOYK',
        '河北农村信用社' => 'HBRCU',
        '甘肃省农村信用社联合社' => 'GSRCU',
        '甘肃银行' => 'GSBANK',
        '辽宁省农村信用社联合社' => 'LNRCC',
        '莱商银行' => 'LSBANK',
        '宁夏银行' => 'NXBANK',
        '张家港农村商业银行' => 'ZRCBANK',
        '新疆银行' => 'XJB',
        '内蒙古银行' => 'H3CB',
        '廊坊银行' => 'LANGFB',
        '临商银行' => 'LSBC',
        '海南银行' => 'HNBANK',
        '德阳商业银行' => 'DYCB',
        '鄞州银行' => 'NBYZ',
        '汉口银行' => 'HKB',
        '安徽省农村信用社' => 'ARCU',
        '华融湘江银行' => 'HRXJB',
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $post = [
            'storeCode'    => intval($data['merchant']) ?? intval($this->merchant),
            'payType'      => $this->channelCodeMap[$this->channelCode],
            'totalAmt'     => $data['request']->amount,
            'notifyUrl'    => $data['callback_url'],
            'storeOrderNo' => $data['request']->order_number,
            'backUrl'      => 'ok'
        ];

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
            $post['playerName'] = $data['request']->real_name;
        }

        $post['sign'] = $this->makesign($post);
        //$post['backUrl'] = '';
        Log::debug(self::class, compact('post'));

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $post
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post', 'row'));

        if (!isset($row['errorCode'])) {
            $ret = [
                'order_number' => $data['request']->order_number,
                'amount' => $row['totalAmt'],
                'pay_url'   => $row['payUrl'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            return ['success' => true, 'data' => $ret];
        } else {
            return ['success' => false];
        }
    }

    public function queryDeposit($data)
    {
        return ['success' => true,'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
        $this->key = $data['key'];

        $post_data = [
            'storeCode'     => intval($data['merchant']) ?? intval($this->merchant),
            'transAmt'      => $data['request']->amount,
            'storeOrderNo'  => $data['request']->order_number,
            'notifyUrl'     => $data['callback_url'],
            'accountName'   => $data['request']->bank_card_holder_name,
            'accountNo'     => $data['request']->bank_card_number,
            'bankCode'      => $this->bankMap[$data['request']->bank_name],
        ];

        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['url'], [
                'json' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false];
        }

        Log::debug(self::class, compact('data', 'post_data', 'row'));

        if (!isset($row['errorCode'])) {
            return ['success' => true];
        }

        return ['success' => false];
    }

    public function queryDaifu($data)
    {

        $post_data = [
            'storeOrderNo'      => $data['request']->order_number,
            'storeCode'         => intval($data['merchant']) ?? intval($this->merchant),
        ];
        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryDaifuUrl'], [
                'json' => $post_data
            ]);
            $row = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }

        Log::debug(self::class, compact('data', 'post_data', 'response'));

        if (!isset($row['errorCode'])) {
            return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }

        return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
        $data = $request->all();

        if ($data['storeOrderNo'] != $transaction->order_number) {
            return ['error' => '订单编号不正确'];
        }

        if (isset($data['totalAmt']) && $data['totalAmt'] != $transaction->amount) {
            return ['error' => '金额不正确'];
        }

        if (isset($data['payResult']) && in_array($data['payResult'],['success'])) {
            return ['success' => true];
        }

        if (isset($data['payResult']) && in_array($data['payResult'],['fail','fail_reject'])) {
            return ['fail' => '驳回'];
        }

        return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $post_data = [
            'storeCode' => $data['merchant']
        ];

        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryBalanceUrl'], [
                'json' => $post_data
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (!isset($result['errorCode'])) {
            $balance = $result['datas'][0]['availableAmt'];

            ThirdChannelModel::where('id', $data['thirdchannelId'])->update(['balance' => $balance]);

            return $balance;
        } else {
            return 0;
        }
    }

    public function makesign($data){
        ksort($data);
        $signstr = '';
        foreach ($data as $k => $v) {
            if ($v != null && $v != "") {
                $signstr = $signstr . $k . "=" . $v . "&";
            }
        }
        return strtoupper(md5($signstr . "key=" . $this->key));
    }
}
