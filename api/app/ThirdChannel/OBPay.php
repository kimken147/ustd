<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Utils\BCMathUtil;
use App\Model\ThirdChannel as ThirdChannelModel;
use App\Model\Transaction;

class OBPay extends ThirdChannel
{
  //Log名称
  public $log_name   = 'OBPay';
  public $type    = 1; //1:代收付 2:纯代收 3:纯代付

  //回调地址
  public $notify    = '';
  public $depositUrl   = 'http://payapi.g4ds4jse.zypay.cc:6080/api/obpay/get_cashierurl';
  public $xiafaUrl   = 'http://payapi.g4ds4jse.zypay.cc:6080/api/obpay/transfer';
  public $daifuUrl   = 'http://payapi.g4ds4jse.zypay.cc:6080/api/obpay/transfer';
  public $queryDepositUrl = 'http://payapi.g4ds4jse.zypay.cc:6080/api/obpay/getremitorder';
  public $queryDaifuUrl  = 'http://payapi.g4ds4jse.zypay.cc:6080/api/obpay/getinterorderV2';
  public $queryBalanceUrl = 'http://payapi.g4ds4jse.zypay.cc:6080/api/agentpay/query_balance';

  //预设商户号
  public $merchant    = '';

  //预设密钥
  public $key         = '';

  //回传字串
  public $success = 'SUCCESS';

  //白名单
  public $whiteIP = [''];

  public $channelCodeMap = [
    'BANK_CARD' => 1,
  ];

  public $bankMap = [
    '工商银行' => 'ICBC',
    '建设银行' => 'CCB',
    '农业银行' => 'ABC',
    '中国银行' => 'BOC',
    '交通银行' => 'BOCM',
    '邮政银行' => 'PSBC',
    '中国邮政储蓄银行' => 'PSBC',
    '平安银行' => 'PAB',
    '中信银行' => 'ECITIC',
    '招商银行' => 'CMB',
    '民生银行' => 'CMBC',
    '浦发银行' => 'SPDB',
    '光大银行' => 'CEB',
    '兴业银行' => 'CIB',
    '广发银行' => 'CGB',
    '华夏银行' => 'HXB',
    '北京银行' => 'BOB',
    '浙商银行' => 'CZBANK',
    '晋商银行' => 'JSB',
    '徽商银行' => 'HSBANK',
    '富滇银行' => 'FDB',
    '东亚银行' => 'HKBEA',
    '恒丰银行' => 'EGBANK',
    '渤海银行' => 'BOHAIB',
    '汇丰银行' => 'HSBC',
    '花旗银行' => 'CITIBANK',
    '北京农商行' => 'BJRCB',
    '鞍山银行' => 'ASCB',
    '包商银行' => 'BSB',
    '保定银行' => 'BDCB',
    '成都银行' => 'CDCB',
    '长沙银行' => 'CSCB',
    '重庆银行' => 'CQBANK',
    '承德银行' => 'BOCD',
    '沧州银行' => 'BOCZ',
    '朝阳银行' => 'BOCY',
    '长安银行' => 'CABANK',
    '大连银行' => 'DLB',
    '东营银行' => 'DYCCB',
    '德州银行' => 'DZBANK',
    '长城华西银行' => 'DYCB',
    '东莞银行' => 'BOD',
    '鄂尔多斯银行' => 'ORBANK',
    '阜新银行' => 'FXCB',
    '福建海峡银行' => 'FJHXBC',
    '富邦华一银行' => 'FBBANK',
    '赣州银行' => 'GANZB',
    '贵阳银行' => 'GYCB',
    '甘肃银行' => 'GSBANK',
    '广东华兴银行' => 'GDHXB',
    '广州银行' => 'GZB',
    '贵州银行' => 'ZYCBANK',
    '桂林银行' => 'GLBANK',
    '湖北银行' => 'HBC',
    '葫芦岛银行' => 'HLDB',
    '河北银行' => 'BHB',
    '杭州银行' => 'HZCB',
    '哈尔滨银行' =>  'HRBANK',
    '邯郸银行' => 'HDBANK',
    '汉口银行' => 'HKB',
    '湖州银行' => 'HZCCB',
    '江苏银行' => 'JSBANK',
    '金华银行' => 'JHBANK',
    '锦州银行' => 'BOJZ',
    '晋中银行' => 'JZBANK',
    '吉林银行' => 'JLBANK',
    '九江银行' => 'JJBANK',
    '江西银行' => 'NCB',
    '济宁银行' => 'JNBANK',
    '晋城银行' => 'JINCHB',
    '嘉兴银行' => 'JXBANK',
    '昆仑银行' => 'KLB',
    '莱商银行' => 'LSBANK',
    '临商银行' => 'LSBC',
    '廊坊银行' => 'LANGFB',
    '漯河银行' => 'LHBANK',
    '辽阳银行' => 'LYCB',
    '龙江银行' => 'DAQINGB',
    '兰州银行' => 'LZYH',
    '洛阳银行' => 'BOL',
    '柳州银行' => 'LZCCB',
    '内蒙古银行' => 'H3CB',
    '四川天府银行' => 'CGNB',
    '宁夏银行' => 'NXBANK',
    '宁波银行' => 'NBBANK',
    '南京银行' => 'NJCB',
    '平顶山银行' => 'BOP',
    '齐鲁银行' => 'QLBANK',
    '青岛银行' => 'QDCCB',
    '泉州银行' => 'BOQZ',
    '青海银行' => 'BOQH',
    '齐商银行' => 'ZBCB',
    '日照银行' => 'RZB',
    '上海银行' => 'SHBANK',
    '石嘴山银行' => 'SZSBK',
    '盛京银行' => 'SJBANK',
    '遂宁银行' => 'SNCCB',
    '上饶银行' => 'SRBANK',
    '苏州银行' => 'BOSZ',
    '绍兴银行' => 'SXCB',
    '泰安银行' => 'TACCB',
    '天津银行' => 'TCCB',
    '台州银行' => 'TZCB',
    '乌海银行' => 'WHBANK',
    '外换银行' => 'KEB',
    '潍坊银行' => 'BANKWF',
    '温州银行' => 'WZCB',
    '网商银行' => 'ANTBANK',
    '乌鲁木齐银行' => 'URMQCCB',
    '厦门银行' => 'XMBANK',
    '邢台银行' => 'XTB',
    '西安银行' => 'XABANK',
    '新韩银行' => 'BOSH',
    '营口银行' => 'BOYK',
    '友利银行' => 'WOORI',
    '烟台银行' => 'YTBANK',
    '云南红塔银行' => 'YXCCB',
    '珠海华润银行' => 'RBOZ',
    '自贡银行' => 'ZGCCB',
    '中原银行' => 'ZYB',
    '张家口银行' => 'ZJKCCB',
    '郑州银行' => 'ZZBANK',
    '内蒙古农村信用社联合社' => 'NMGNXS',
    '江西省农村信用社' => 'JXRCU',
    '山西省农村信用社' => 'SXRCU',
    '黑龙江省农村信用社联合社' => 'HLJRCU',
    '河北省农村信用社' => 'HBRCU',
    '河南省农村信用社' => 'HNRCU',
    '辽宁省农村信用社' => 'LNRCC',
    '贵阳银行' => 'GYCB',
    '云南省农村信用社' => 'YNRCC',
    '新疆农村信用社' => 'XJRCU',
    '广西壮族自治区农村信用社联合社' => 'GXRCU',
    '福建省农村信用社联合社' => 'FJNX',
    '湖南省农村信用社' => 'HNRCC',
    '海南省农村信用社' => 'BOHN',
    '山西省农村信用社' => 'SXRCU',
    '甘肃省农村信用社' => 'GSRCU',
    '北京银行' => 'BJBANK',
    '深圳农村商业银行' => 'SRCB',
    '重庆农村商业银行' => 'CRCBANK',
    '贵州省农村信用社联合社' => 'GZRCU',
    '湖北省农村信用社' => 'HBNXSBANK',
    '浙江农信' => 'ZJNX',
    '江西农商银行' => 'JXNSB',
    '浙江省农村信用社联合社' => 'ZJSNCXYSLHSBANK',
    '山东农商银行' => 'SDNSB',
    '广东农信' => 'GDNXB',
    '广州农商银行' => 'GZNSB',
    '广东华兴银行' => 'GDHXB',
    '上海农商银行' => 'SHNSYXBANK',
    '昆山农商银行' => 'KSNSB',
    '徽商银行' => 'HSYXBANK',
    '深圳农商行' => 'SZNSHB',
    '国民村镇银行' => 'GMCZB',
    '广西农信' => 'GXNXB',
    '广西北部湾银行' => 'GXBBWB',
    '四川农信' => 'SCNXB',
    '张家港农商银行' => 'ZJGNXB',
    '苏州银行' => 'BOSZ',
    '江苏农信' => 'JSNXB',
    '湖南农信' => 'HUNNXB',
    '海南农信社' => 'HAINNXSB',
    '贵州农信' => 'GZNXB',
    '攀枝花市商业银行' => 'PZHSSYYXBANK',
    '石嘴山银行' => 'SZSBK',
    '德州银行' => 'DZBANK',
    '天津滨海农商银行' => 'TJBHNSB',
    '浙江泰隆银行' => 'TLCSXYSBANK',
    '吉林农信' => 'JLNXB',
    '内蒙古农信' => 'NMGNXS',
    '方城凤裕村镇银行' => 'FCFYCZB',
    '重庆三峡银行' => 'ZQSXYXBANK',
    '唐山银行' => 'TSB',
    '北京农商银行' => 'BOBJSY',
    '东莞农商银行' => 'DGNXB',
    '吉林省农村信用社联合社' => 'JLSNCXYSLHSBANK',
    '武汉农商行' => 'WHNSHB',
    '陕西信合' => 'SXXHB',
    '广西农村信用社联合社' => 'GXNCXYSLHSBANK',
    '黑龙江省农村信用社联合社' => 'HLJSNCXYSLHSBANK',
    '海南银行' => 'HNB',
    '甘肃省农村信用社联合社' => 'GSSNCXYSLHSBANK',
    '上海银行' => 'SHB',
    '日照银行' => 'RZYXBANK',
    '广东省农村信用社联合社' => 'GDSNCXYSLHSBANK',
    '湖南省农村信用社' => 'HNSNCXYSLHSBANK',
    '浙江稠州商业银行' => 'ZJCZSYYXBANK',
    '安徽农金' => 'AHNJB',
    '海丰农商银行' => 'HFNSB',
    '银座银行' => 'YZB',
    '甘肃信合' => 'GSXHB',
    '河北农信' => 'HBNXB',
    '安徽省农村信用社' => 'AHSNCXYSBANK',
    '深圳农村商业银行' => 'SZNCSYYXBANK',
    '常熟农商银行' => 'CSNSB',
    '湖商村镇银行' => 'HNCZB',
    '天津农商银行' => 'TJNSB',
    '山东省农村信用社联合社' => 'SDNCXYSLHSBANK',
    '韶关农商银行' => 'SGNSB',
    '达州银行' => 'DZB',
    '威海商业银行' => 'WHSYBANK',
    '广东南海农村商业银行' => 'GDNHNCSYYXBANK',
    '哈尔滨农村商业银行' => 'HEBNCSYYXBANK',
    '青海省农村信用社联合社' => 'QHNCXYSBANK',
    '焦作中旅银行' => 'JZZLB',
    '成都农村商业银行股份有限公司' => 'CDNCSYYHBANK',
    '山西银行' => 'SXB',
    '黑龙江农信' => 'HLJNXB',
    '宁夏农村信用社' => 'NXNCXYSBANK',
    '顺德农商银行' => 'SDNSDBG',
    '华融湘江银行' => 'HRXJYXBANK',
    '北京顺义银座村镇银行' => 'BJSYYZCZYXBANK',
    '广东顺德农村商业银行' => 'GDSDNCSYYXBANK',
    '浙江民泰商业银行' => 'ZJMTSYYXBANK',
    '山西农信' => 'SXRCU',
    '河南农信' => 'HNNXB',
    '广州农村商业银行' => 'GZNCSY',
    '云南农信' => 'YNNXB',
    '广西农信' => 'GXNX',
    '江西裕民银行' => 'JXYMB',
    '成都农商银行' => 'CDNSB',
    '威海市商业银行' => 'WHBB',
    '福建农信' => 'FJNXB',
    '福建农商银行' => 'FJNSB',
    '昆明农联社' => 'KMNLSBANK',
    '浙信村镇银行' => 'ZJCZB',
    '安源富民村镇银行' => 'AYFMCB',
    '无锡农商行' => 'WXNSB',
    '汉口银行' => 'HKYH',
    '广东南粤银行' => 'GDNYYXBANK',
    '盘锦银行' => 'PJB',
    '贵阳农商银行' => 'GYNSB',
    '常熟农村商业银行' => 'CSNCSYYXBANK',
    '辽宁农信' => 'LNNXB',
    '鄞州银行' => 'YZZJB',
    '中银富登' => 'ZYFDB',
    '宁波通商银行' => 'NBTSB',
    '众邦银行' => 'ZBB',
    '桂林国民村镇银行' => 'GLGMCZB',
    '东海村镇银行' => 'DHCZB',
    '三河蒙银' => 'SHMYB',
    '苏州农商银行' => 'SZNSB',
    '江苏省农村信用社联合社行' => 'JSSNCXYSLHSBANK',
    '长江银行' => 'CJB',
    '南昌银行' => 'NCYHBANK',
    '丹东银行' => 'DDB',
    '陕西省农村信用社联合社' => 'SXSNCXYSLHSBANK',
  ];

  /*   代收   */
  public function sendDeposit($data)
  {
    $this->key = $data['key'];

    $math = new BCMathUtil;
    $post = [
      'mchId'       => intval($data['merchant']) ?? intval($this->merchant),
      'type'        => intval($this->channelCodeMap[$this->channelCode]),
      'amount'      => $math->mul($data['request']->amount, 100, 0),   // 金額單位是分
      'notifyUrl'   => $data['callback_url'],
      'mchOrderNo'  => $data['request']->order_number,
    ];

    if(isset($data['request']->real_name) && $data['request']->real_name != ''){
      $post['realName'] = $data['request']->real_name;
    }

    $post['sign'] = $this->makesign($post);

    Log::debug(self::class, compact('post'));

    $return_data = json_decode($this->curl($data['url'],http_build_query($post)),true);

    Log::debug(self::class, ['order' => $data['request']->order_number, 'return_data' => $return_data]);

    if (isset($return_data['retCode']) && in_array($return_data['retCode'], ['SUCCESS'])) {
      $ret = [
        'pay_url' => $return_data['data']['cashier'],
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
    if (!isset($this->bankMap[$data['request']->bank_name])) {
      return ['success' => false, 'msg' => '不支持此银行代付，请联系客服'];
    }

    $this->key = $data['key'];

    $math = new BCMathUtil;
    $post_data = [
      'mchId'        => intval($data['merchant']) ?? intval($this->merchant),
      'amount'       => $math->mul($data['request']->amount, 100, 0),    // 金額單位是分
      'mchOrderNo'   => $data['request']->order_number,
      'notifyUrl'    => $data['callback_url'],
      'trueName'     => $data['request']->bank_card_holder_name,
      'cardNo'       => $data['request']->bank_card_number,
      'bankType'     => $this->bankMap[$data['request']->bank_name],
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

    $post_data = [
      'mchOrderNo'   => $data['request']->order_number,
      'mchId'        => intval($data['merchant']) ?? intval($this->merchant),
    ];
    $post_data['sign'] = $this->makesign($post_data);
    $return_data = json_decode($this->curl($data['queryDaifuUrl'],http_build_query($post_data)),true);

    Log::debug(self::class, compact('post_data', 'return_data'));

    if (isset($return_data['retCode']) && in_array($return_data['retCode'],['SUCCESS'])) {
      return ['success' => true, 'status' => Transaction::STATUS_PAYING];
    } else {
      return ['success' => false, 'status' => Transaction::STATUS_PAYING];
    }
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

    if (isset($data['status']) && in_array($data['status'],[1,2])) {
      return ['success' => true];
    }

    if (isset($data['status']) && in_array($data['status'],[3])) {
      return ['fail' => '支付失败'];
    }

    return ['error' => '未知错误'];
  }

  public function queryBalance($data)
  {
    $math = new BCMathUtil;
    $this->key = $data['key'];
    $post_data = [
        'mchId'   => $data['merchant'],
        'reqTime' => now()->format('YmdHis')
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

    if (isset($result['retCode']) && in_array($result['retCode'],['SUCCESS'])) {
        $balance = $math->div($result['availableAgentpayBalance'], 100, 2);

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
