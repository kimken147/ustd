<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;

class Sby extends ThirdChannel
{
    //Log名称
    public $log_name   = 'Sby';
    public $type    = 1; //1:代收付 2:纯代收 3:纯代付

    //回调地址
    public $notify    = '';
    public $depositUrl   = 'http://api.suibianyun.com/pay/DepositOrder';
    public $xiafaUrl   = 'http://api.suibianyun.com/pay/WithdrawOrder';
    public $daifuUrl   = 'http://api.suibianyun.com/pay/WithdrawOrder';
    public $queryDepositUrl = 'http://api.suibianyun.com//pay/QueryOrder';
    public $queryDaifuUrl  = 'http://api.suibianyun.com/pay/QueryWithdrawOrder';
    public $queryBalanceUrl = 'http://api.suibianyun.com/pay/QueryMerchantBalance';

    //预设商户号
    public $merchant    = '';

    //预设密钥
    public $key         = '';

    //回传字串
    public $success = 'SUCCESS';

    //白名单
    public $whiteIP = [''];

    public $channelCodeMap = [
      'BANK_CARD' => '1011'
    ];

    public $bankMap = [
      '华夏银行' => 'HXBANK',
      '广发银行' => 'CGB',
      '光大银行' => 'CEB',
      '民生银行' => 'CMBC',
      '中信银行' => 'CNCB',
      '兴业银行' => 'CIB',
      '中国邮政储蓄银行' => 'PSBC',
      '邮政银行' => 'PSBC',
      '交通银行' => 'BCM',
      '建设银行' => 'CCB',
      '中国银行' => 'BOC',
      '招商银行' => 'CMB',
      '农业银行' => 'ABC',
      '平安银行' => 'PAB',
      '工商银行' => 'ICBC',
      '浦发银行' => 'SPDB',
    ];

    /*   代收   */
    public function sendDeposit($data)
    {
        $this->key = $data['key'];

        $post = [
          'account'         => $data['merchant'] ?? $this->merchant,
          'paytype'         => intval($this->channelCodeMap[$this->channelCode]),
          'amount'          => $data['request']->amount,
          'callbackurl'     => $data['callback_url'],
          'hrefbackurl'     => 'url',
          'orderno'         => $data['request']->order_number,
          'ip'              => $data['request']->client_ip ?? $data['client_ip']
        ];

        $post['sign'] = $this->makesign($post);
        $post['returntype'] = 'json';

        if(isset($data['request']->real_name) && $data['request']->real_name != ''){
          $post['payname'] = $data['request']->real_name;
        }

        Log::debug(self::class, compact('post'));

        $return_data = json_decode($this->curl($data['url'],http_build_query($post)),true);

        Log::debug(self::class, ['order' => $data['request']->order_number, 'return_data' => $return_data]);

        if(isset($return_data['code']) && in_array($return_data['code'], ['0000'])){
          $ret = [
            'pay_url' => $return_data['url']
          ];

        }else{
          return ['success' => false];
        }
        return ['success' => true,'data' => $ret];
    }

    public function queryDeposit($data)
    {
        return ['success' => true,'msg' => '原因'];
    }

    /*   代付   */
    public function sendDaifu($data)
    {
      if (!isset($this->bankMap[$data['request']->bank_name])) {
        return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
      }

      $this->key = $data['key'];

      $post_data = [
        'account'       => $data['merchant'] ?? $this->merchant,
        'amount'        => $data['request']->amount,
        'orderno'       => $data['request']->order_number,
        'callbackurl'   => $data['callback_url'],
        'cardname'      => $data['request']->bank_card_holder_name,
        'cardno'        => $data['request']->bank_card_number,
        'bankcode'      => $this->bankMap[$data['request']->bank_name],
      ];

      $post_data['sign'] = $this->makesign($post_data);
      $return_data = json_decode($this->curl($data['url'],http_build_query($post_data)),true);

      Log::debug(self::class, compact('post_data', 'return_data'));

      if(isset($return_data['code']) && in_array($return_data['code'],['0000'])){
        return ['success' => true];
      }else{
        return ['success' => false];
      }
      return ['success' => false];
    }

    public function queryDaifu($data)
    {

        $post_data = [
          'orderno'   => $data['request']->order_number,
          'account'   => $data['merchant'] ?? $this->merchant,
        ];
        $post_data['sign'] = $this->makesign($post_data);
        $return_data = json_decode($this->curl($data['queryDaifuUrl'],http_build_query($post_data)),true);

        Log::debug(self::class, compact('post_data', 'return_data'));

        if(isset($return_data['code']) && in_array($return_data['code'],[0])){
          return ['success' => true, 'status' => Transaction::STATUS_PAYING];
        }else{
          return ['success' => false, 'status' => Transaction::STATUS_PAYING];
        }
    }

    /*   回调 => callback($request,訂單資料)   */
    public function callback($request, $transaction)
    {
      $data = $request->all();

      if ($data['orderno'] != $transaction->order_number) {
        return ['error' => '订单编号不正确'];
      }

      if (isset($data['amount']) && $data['amount'] != $transaction->amount) {
        return ['error' => '金额不正确'];
      }

      if (isset($data['orderstatus']) && in_array($data['orderstatus'],[1])) {
        return ['success' => true];
      }

      if (isset($data['orderstatus']) && in_array($data['orderstatus'],[2,3])) {
        $map = [
          2 => '失败',
          3 => '冲正',
        ];
        return ['fail' => '驳回'];
      }

      return ['error' => '未知错误'];
    }

    public function queryBalance($data)
    {
        $this->key = $data['key'];
        $post_data = [
            'account' => $data['merchant']
        ];

        $post_data['sign'] = $this->makesign($post_data);

        try {
            $client = new \GuzzleHttp\Client();
            $response = $client->request('POST', $data['queryBalanceUrl'], [
                'form_params' => $post_data
            ]);
            $result = json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            Log::error(self::class, compact('data', 'post_data', 'message'));
            return 0;
        }

        Log::debug(self::class, compact('data', 'post_data', 'result'));

        if (isset($result['code']) && in_array($result['code'],[0])) {
            $balance = $result['balance'];
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
      return md5($signstr . "key=" . $this->key);
    }
}
