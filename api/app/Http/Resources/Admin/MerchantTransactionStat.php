<?php

namespace App\Http\Resources\Admin;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantTransactionStat extends JsonResource
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
            'id'                          => $this->getKey(),
            'name'                        => $this->name,
            'has_children'                => !$this->isLeaf(),
            'yesterday_self_total'        => AmountDisplayTransformer::transform($this->yesterday_self_total),
            'today_self_total'            => AmountDisplayTransformer::transform($this->today_self_total),
            'yesterday_descendants_total' => AmountDisplayTransformer::transform($this->yesterday_descendants_total),
            'today_descendants_total'     => AmountDisplayTransformer::transform($this->today_descendants_total),
            'descendants_total'           => AmountDisplayTransformer::transform($this->descendants_total),
            'balance_total'               => AmountDisplayTransformer::transform($this->balance_total),
        ];
    }
}
