<?php

namespace App\ThirdChannel;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\ThirdChannel as ThirdChannelModel;
use App\Utils\BCMathUtil;
use Illuminate\Support\Arr;

class WeiYun extends ThirdChannel
{
  //Log名称
  public $log_name   = 'WeiYun';
  public $type    = 1; //1:代收付 2:纯代收 3:纯代付

  //回调地址
  public $notify    = '';
  public $depositUrl   = 'http://101.32.205.83:8885/api/pay/order';
  public $xiafaUrl   = 'http://101.32.205.83:8885/api/sett/apply';
  public $daifuUrl   = 'http://101.32.205.83:8885/api/sett/apply';
  public $queryDepositUrl = 'http://101.32.205.83:8885/api/pay/query';
  public $queryDaifuUrl  = 'http://101.32.205.83:8885/api/sett/query';
  public $queryBalanceUrl = 'http://101.32.205.83:8885/api/balance/query';

  //预设商户号
  public $merchant    = '';

  //预设密钥
  public $key         = '';

  //回传字串
  public $success = 'success';

  //白名单
  public $whiteIP = [''];

  public $channelCodeMap = [
    'BANK_CARD' => 8009,
  ];

  /*   代收   */
  public function sendDeposit($data)
  {
    $this->key = $data['key'];

    $math = new BCMathUtil;
    $post = [
      'mchId'       => intval($data['merchant']) ?? intval($this->merchant),
      'productId'   => intval($this->channelCodeMap[$this->channelCode]),
      'amount'      => $math->mul($data['request']->amount, 100, 0),   // 金額單位是分
      'notifyUrl'   => $data['callback_url'],
      'body' => '团购商品',
      'subject' => '团购商品',
      'clientIp'  => $data['request']->client_ip ?? $data['client_ip'],
      'extra' => null,
      'mchOrderNo'  => $data['request']->order_number,
    ];

    if(isset($data['request']->real_name) && $data['request']->real_name != ''){
      $post['extra'] = $data['request']->real_name;
    }

    $post['sign'] = $this->makesign($post);

    Log::debug(self::class, compact('post'));

    $return_data = json_decode($this->curl($data['url'],http_build_query($post)),true);

    Log::debug(self::class, ['order' => $data['request']->order_number, 'return_data' => $return_data]);

    if (isset($return_data['retCode']) && in_array($return_data['retCode'], ['SUCCESS'])) {
      $ret = [
        'pay_url' => $return_data['payUrl'],
      ];
    } else {
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
    $this->key = $data['key'];

    $math = new BCMathUtil;
    $post_data = [
      'mchId'        => intval($data['merchant']) ?? intval($this->merchant),
      'amount'       => $math->mul($data['request']->amount, 100, 0),    // 金額單位是分
      'mchOrderNo'   => $data['request']->order_number,
      'notifyUrl'    => $data['callback_url'],
      'accountName'  => $data['request']->bank_card_holder_name,
      'accountNo'    => $data['request']->bank_card_number,
      'bankName'     => $data['request']->bank_name,
      'remark'       => '代付'
    ];

    $post_data['sign'] = $this->makesign($post_data);
    $return_data = json_decode($this->curl($data['url'],http_build_query($post_data)),true);

    Log::debug(self::class, compact('post_data', 'return_data'));

    if (isset($return_data['retCode']) && in_array($return_data['retCode'],['SUCCESS'])) {
      return ['success' => true];
    } else {
      return ['success' => false];
    }
    return ['success' => false];
  }

  public function queryDaifu($data)
  {
    $this->key = $data['key'];

    $post_data = [
      'mchOrderNo'   => $data['request']->order_number,
      'mchId'        => intval($data['merchant']) ?? intval($this->merchant),
    ];
    $post_data['sign'] = $this->makesign($post_data);
    $return_data = json_decode($this->curl($data['queryDaifuUrl'],http_build_query($post_data)),true);

    Log::debug(self::class, compact('post_data', 'return_data'));

    if (isset($return_data['retCode']) && in_array($return_data['retCode'],['SUCCESS'])) {
      if (isset($return_data['status']) && in_array($return_data['status'],[3, 6])) {
        return ['success' => true, 'msg' => '支付失败', 'status' => Transaction::STATUS_FAILED];
      }

      if (isset($return_data['status']) && in_array($return_data['status'],[7])) {
        return ['success' => true, 'msg' => '支付成功', 'status' => Transaction::STATUS_PAYING];
      }
    }
    return ['success' => false, 'status' => Transaction::STATUS_PAYING];
  }

  /*   回调 => callback($request,訂單資料)   */
  public function callback($request, $transaction)
  {
    $math = new BCMathUtil;
    $data = $request->all();

    if ($data['mchOrderNo'] != $transaction->order_number) {
      return ['error' => '订单编号不正确'];
    }

    if (isset($data['amount']) && $data['amount'] != $math->mul($transaction->amount, 100, 0)) {   // 金額單位是分
      return ['error' => '金额不正确'];
    }

    if ($transaction->to_id) { //代收
      if (isset($data['status']) && in_array($data['status'],[2, 3])) {
        return ['success' => true];
      }
    }

    if ($transaction->from_id) { //代付
      if (isset($data['status']) && in_array($data['status'],[5])) {
        return ['success' => true];
      }

      if (isset($data['status']) && in_array($data['status'],[3, 6])) {
        return ['fail' => '支付失败'];
      }
    }

    return ['error' => '未知错误'];
  }

  public function queryBalance($data)
  {
    $this->key = $data['key'];

    $math = new BCMathUtil;
    $post_data = [
        'mchId'        => intval($data['merchant']) ?? intval($this->merchant)
    ];
    $post_data['sign'] = $this->makesign($post_data);
    $return_data = json_decode($this->curl($data['queryBalanceUrl'],http_build_query($post_data)),true);

    Log::debug(self::class, compact('data', 'post_data', 'return_data'));

    if (isset($return_data['retCode']) && in_array($return_data['retCode'],['SUCCESS'])) {
        $balance = $math->div($return_data['balance'], 100, 2);

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

    $secrtKey = strtoupper(md5($this->key));

    return strtoupper(md5($signstr . "secretKey=" . $secrtKey));
  }
}
