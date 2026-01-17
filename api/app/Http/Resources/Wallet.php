<?php

namespace App\Http\Resources;

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
            'balance'           => AmountDisplayTransformer::transform($this->balance),
            'profit'            => AmountDisplayTransformer::transform($this->profit),
            'frozen_balance'    => AmountDisplayTransformer::transform($this->frozen_balance),
            'available_balance' => AmountDisplayTransformer::transform($this->available_balance),
        ];
    }
}
