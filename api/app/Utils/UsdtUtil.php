<?php

namespace App\Utils;

use App\Model\FeatureToggle;
use App\Utils\GuzzleHttpClientTrait;
use App\Repository\FeatureToggleRepository;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class UsdtUtil
{
    use GuzzleHttpClientTrait;

    private $featureToggleRepository;
    private $math;

    public function __construct(FeatureToggleRepository $featureToggleRepository, BCMathUtil $math)
    {
       $this->featureToggleRepository = $featureToggleRepository;
       $this->math = $math;
    }

    public function getRate($currency = 'CNY')
    {
        try {
            $cacheKey = "rate:usdt:${currency}";

            $quotation = Redis::get($cacheKey);

            if (!$quotation) {
                $response = $this->makeClient()
                ->post('https://www.binance.com/bapi/fiat/v2/friendly/ocbs/buy/list-crypto', [
                    RequestOptions::JSON => [
                        'channels' => ["p2p","wallet","card","mobilum","ONLINE_BANKING","INSWITCH"],
                        "fiat" => $currency,
                        "transactionType" => "buy"
                    ]
                ]);

                $quotation = collect(json_decode($response->getBody(), true)['data'])->firstWhere('assetCode', 'USDT')['quotation'];

                Redis::set($cacheKey, $quotation, 'EX', 5);
            }

            if ($this->featureToggleRepository->enabled(FeatureToggle::USDT_ADD_RATE)) {
                $quotation = $this->math->add($quotation, $this->featureToggleRepository->valueOf(FeatureToggle::USDT_ADD_RATE), 2);
            } else {
                $quotation = $this->math->add($quotation, 0, 2);
            }

        } catch (\Exception $e) {
            $quotation = 0;

            Log::debug(__CLASS__, [
                'message'             => 'Get USDT rate failed with exception',
                'exception'           => $e,
            ]);
        }

        return ['rate' => $quotation];
    }
}