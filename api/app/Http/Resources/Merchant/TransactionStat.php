<?php

namespace App\Http\Resources\Merchant;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionStat extends JsonResource
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
            'username'                    => $this->username,
            'has_children'                => ($this->getKey() === auth()->user()->getKey() && !$this->isLeaf()), // 只允許往下看一層
            'yesterday_self_total'        => AmountDisplayTransformer::transform($this->yesterday_self_total),
            'today_self_total'            => AmountDisplayTransformer::transform($this->today_self_total),
            'yesterday_descendants_total' => AmountDisplayTransformer::transform($this->yesterday_descendants_total),
            'today_descendants_total'     => AmountDisplayTransformer::transform($this->today_descendants_total),
        ];
    }
}
