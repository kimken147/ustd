<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionReward extends JsonResource
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
            'id'            => $this->getKey(),
            'min_amount'    => $this->min_amount,
            'max_amount'    => $this->max_amount,
            'reward_amount' => $this->reward_amount,
            'reward_unit'   => $this->reward_unit,
            'started_at'    => $this->started_at,
            'ended_at'      => $this->ended_at,
            'updated_at'    => $this->updated_at->toIso8601String(),
            'created_at'    => $this->created_at->toIso8601String(),
        ];
    }
}
