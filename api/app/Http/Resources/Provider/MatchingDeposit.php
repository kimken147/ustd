<?php

namespace App\Http\Resources\Provider;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchingDeposit extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                    => $this->getKey(),
            'amount'                => AmountDisplayTransformer::transform($this->amount),
            'from_channel_account'  => $this->from_channel_account
        ];
    }
}
