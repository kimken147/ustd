<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Utils\BankCardNumberRecognizerTrait;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BankCardNumberController extends Controller
{

    use BankCardNumberRecognizerTrait;

    public function show($bankCardNumber)
    {
        abort_if(
            empty($bankName = $this->bankName($bankCardNumber)),
            Response::HTTP_NOT_FOUND,
            __('bank-card.Unable to resolve bank name from card number')
        );

        return response()->json([
            'data' => [
                'bank_name' => $bankName,
            ],
        ]);
    }

    private function bankName($bankCardNumber)
    {
        $fullCardNumberCacheKey = "bank_card_number_to_bank_name_$bankCardNumber";

        $binCodeOfBankCardNumber = Str::substr($bankCardNumber, 0, 6);
        $binCodeCacheKey = "bank_card_number_to_bank_name_$binCodeOfBankCardNumber";

        // 若 BinCode 快取命中，代表曾經有正確的查詢結果，就不需要再發送一次 API，避免被 Alipay 禁止
        if ($cachedBankName = Cache::get($binCodeCacheKey)) {
            return $cachedBankName;
        }

        // 有整張卡號快取的記錄，代表該卡號已經查過，且不是正確的卡號，避免重複發送到 Alipay
        // 這邊用 Has 的原因是 cache 內容是空字串，會直接被當 falsy 判掉
        if (Cache::has($fullCardNumberCacheKey)) {
            return Cache::get($fullCardNumberCacheKey);
        }

        // 沒有命中時，發送 Alipay API 查詢
        try {
            $response = $this->makeClient()
                ->get('https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?'.http_build_query([
                        '_input_charset' => 'utf-8',
                        'cardNo'         => $bankCardNumber,
                        'cardBinCheck'   => 'true',
                    ]));

            $responseData = json_decode($response->getBody()->getContents());

            $bankName = $this->alipayBankCodeToBankName(data_get($responseData, 'bank'));

            // 只有 API 查詢結果正確的狀況下，再做 Cache
            if (!empty($bankName)) {
                Cache::put($binCodeCacheKey, $bankName, now()->addDay());

                return $bankName;
            }
        } catch (TransferException $transferException) {
            Log::debug($transferException->getMessage(), [self::class, $bankCardNumber]);

            // 如果是連線問題，不要快取該卡號
            return '';
        }

        Cache::put($fullCardNumberCacheKey, '', now()->addDay());

        return '';
    }

    private function alipayBankCodeToBankName($alipayBankCode)
    {
        if (empty($alipayBankCode)) {
            return '';
        }

        return data_get([
            'BJBANK'  => '北京银行',
            'SPABANK' => '平安银行',
            'CZBANK'  => '浙商银行',
            'BOC'     => '中国银行',
            'CCB'     => '建设银行',
            'SPDB'    => '浦发银行',
            'HZCB'    => '杭州银行',
            'NJCB'    => '南京银行',
            'FDB'     => '富滇银行',
            'CMB'     => '招商银行',
            'CEB'     => '光大银行',
            'HSBANK'  => '徽商银行',
            'NBBANK'  => '宁波银行',
            'HKBEA'   => '香港东亚银行',
            'SHBANK'  => '上海银行',
            'BOHAIB'  => '渤海银行',
            'CZCB'    => '浙江稠州商业银行',
            'COMM'    => '交通银行',
            'CITIC'   => '中信银行',
            'HXBANK'  => '华夏银行',
            'BJRCB'   => '北京农商银行',
            'CMBC'    => '民生银行',
            'GDB'     => '广发银行',
            'CIB'     => '兴业银行',
            'ICBC'    => '工商银行',
            'ABC'     => '农业银行',
            'PSBC'    => '中国邮政储蓄银行',
            'BHB'     => '河北银行',
        ], $alipayBankCode, '');
    }
}
