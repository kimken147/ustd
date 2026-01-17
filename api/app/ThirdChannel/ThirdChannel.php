<?php

namespace App\ThirdChannel;

use App\Utils\BCMathUtil;

class ThirdChannel
{
    public $type                = 1;    //1:代收付 2:纯代收 3:纯代付

    public $notify                = '';
    public $depositUrl            = 'https://api.wmh168.com/api/v1/third-party/create-transactions';
    public $daifuUrl            = 'https://api.wmh168.com/api/v1/third-party/withdraws';
    public $queryDepositUrl    = '';
    public $queryDaifuUrl        = 'https://api.wmh168.com/api/v1/third-party/transaction-queries';
    public $queryBalanceUrl    = '';

    public $merchant    = '';
    public $key         = '';

    public $success = 'success';

    public $whiteIP = [];

    protected $channelCode = 'BANK_CARD';
    protected BCMathUtil $bcMathUtil;

    public function __construct($channelCode = 'BANK_CARD')
    {
        ini_set('max_execution_time', 60); // 可能同時多個請求，改成 60 秒
        $this->channelCode = $channelCode;
        $this->bcMathUtil = app(BCMathUtil::class);
    }

    public function curl($url, $data = '', $ex_header = [], $proxy = '')
    {
        $useragent = 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:53.0) Gecko/20100101 Firefox/53.0';
        $header    = [
            'Accept:*/*',
            'Cache-Control:no-cache',
            'Connection: keep-alive',
            'Cache-Control: no-cache',
            'Pragma:no-cache',
        ];
        if (is_array($ex_header) && count($ex_header) > 0) $header = array_merge($header, $ex_header);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_REFERER, $url);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_USERAGENT, $useragent);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        if (!empty($data)) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        if (!empty($proxy)) {
            curl_setopt($curl, CURLOPT_PROXY, $proxy);
        }
        $return = curl_exec($curl);
        if (curl_errno($curl)) {
            return curl_errno($curl);
        }
        curl_close($curl);
        return $return;
    }

    protected function normalizeChineseCharacters($input)
    {
        $replacements = [
            '⾏' => '行',
            // 可以根据需要添加更多的替换
        ];
        return strtr($input, $replacements);
    }
}
