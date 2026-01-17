<?php

namespace App\Http\Resources\Exchange;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Wallet extends JsonResource
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
            'available_balance' => AmountDisplayTransformer::transform($this->available_balance),
            'btc_balance'       => '0.00',
            'eth_balance'       => '0.00',
            'usdt_balance'      => optional($this->user->fakeUsdtCryptoWallet)->balance ?? '0.00',
        ];
    }
}
