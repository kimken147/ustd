<?php

namespace App\Http\Resources\Exchange;

use App\Utils\AmountDisplayTransformer;
use App\Utils\FakeCryptoExchange;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchingDeposit extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                    => $this->getKey(),
            'amount'                => AmountDisplayTransformer::transform($this->amount),
            'cryptocurrency_amount' => $this->getCryptocurrencyAmount(),
        ];
    }

    private function getCryptocurrencyAmount()
    {
        /** @var FakeCryptoExchange $fakeCryptoExchange */
        $fakeCryptoExchange = app(FakeCryptoExchange::class);

        return AmountDisplayTransformer::transform($fakeCryptoExchange->cnyToUsdt($this->amount)).' USDT';
    }

}
