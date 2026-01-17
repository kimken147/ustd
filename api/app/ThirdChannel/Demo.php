<?php

namespace App\ThirdChannel;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class Demo extends ThirdChannel
{
	//Log名称
	public $log_name			= 'Demo';
	public $type				= 1;	//1:代收付 2:纯代收 3:纯代付

	//回调地址
	public $notify				= '';
	public $deposit_url			= 'https://lailaipay.cc/api/transfer';
	public $daifuUrl			= 'https://lailaipay.cc/api/daifu';
	public $queryDepositUrl	= '';
	public $queryDaifuUrl		= 'https://lailaipay.cc/api/query';
	public $queryBalanceUrl	= '';

	//预设商户号
	public $merchant    = 'merchant';

	//预设密钥
	public $key         = 'f5c39b21ce3e80ffd10e22a5d53175e5b6debd19d42bac6c0c73794a0f4597bd';

	//回传字串
	public $success = 'success';

	//白名单
	public $whiteIP = [];

	public function __construct()
	{

	}

	/*			代收			*/
	public function sendDeposit($data)
	{
		$this->key = $data['key'];
		$post = [
			'merchant'	=> $data['merchant'] ?? $this->merchant,
			'pay_type'	=> 'bankcard',
			'amount'	=> $data['request']->amount,
			'callback_url'	=> $data['callback_url'],
			'order_id'	=> $data['request']->order_number,
		];

		if(isset($data['request']->real_name) && $data['request']->real_name != ''){
			$post['bank_card_name']	= $data['request']->real_name;
		}

		$post['sign'] = md5($this->makesign($post));

		$return_data = json_decode($this->curl($data['url'],http_build_query($post)),true);

		if($return_data['retCode'] == '0000'){
			$ret = [
				'order_number' => $data['request']->order_number,
				'amount' => '',
				'receiver_name' => '',
				'receiver_bank_name' => '',
				'receiver_account' => '',
				'receiver_bank_branch' => '',
				'pay_url'	=> $return_data['data']['url'],
				'created_at' => date('Y-m-d H:i:s'),
			];
		}else{
			return ['success' => false];
		}
		return ['success' => true,'data' => $ret];

		//return ['success' => true,'url' => $req_data['payurl']];
	}

	public function queryDeposit($data)
	{
		return ['success' => true,'msg' => '原因'];
	}

	/*			代付			*/
	public function sendDaifu($data)
	{
	    $post_data = [
	        'order_id'			=> $data['request']->order_number,
	        'merchant'			=> $data['merchant'] ?? $this->merchant,
	        'total_amount'		=> $data['request']->amount,
	        'bank_card_name'	=> $data['request']->bank_card_holder_name,
	        'bank_card_account'	=> $data['request']->bank_card_number,
	        'bank_card_remark'	=> $data['request']->bank_name,	//沒有分行 填銀行
	        'bank' 				=> $data['request']->bank_name,
	        'callback_url'		=> $data['callback_url'],
	    ];

	    $post_data['sign'] = md5($this->makesign($post_data));
	    $return_data = json_decode($this->curl($data['url'],http_build_query($post_data)),true);

		if($return_data['retCode'] == '0000'){
			return ['success' => true];
		}else{
			return ['success' => false];
		}
		return ['success' => false];
	}

	public function queryDaifu($data)
	{
		Log::debug(json_encode($data));
	    $post_data = [
	        'order_id'			=> $data['request']->order_number,
	        'merchant'			=> $data['merchant'] ?? $this->merchant,
	    ];
	    $post_data['sign'] = md5($this->makesign($post_data));
	    $return_data = json_decode($this->curl($data['queryDaifuUrl'],http_build_query($post_data)),true);


		Log::debug($data['request']->order_number . '_QUERY ::' .json_encode($return_data));
		if($return_data['retCode'] == '0000' && in_array($return_data['data']['status'],[1,2])){
			return ['success' => true];
		}else{
			return ['success' => false];
		}
		return ['success' => false];
	}

	/*			回调 => callback($request,訂單資料)			*/
	public function callback($data,$tran)
	{
		//return ['status' => false,'msg' => '原因'];
		return ['success' => true];
	}

	public function queryBalance($data)
	{
		return 99999999;
	}

	public function makesign($data){
	    ksort($data);
	    $signstr = '';
	    foreach ($data as $k => $v) {
	        $signstr .= "$k=$v&";
	    }
	    $signstr .= "key=$this->key";
	    return $signstr;
	}
}
