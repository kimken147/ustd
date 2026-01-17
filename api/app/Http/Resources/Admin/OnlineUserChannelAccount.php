<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\User;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class OnlineUserChannelAccount extends JsonResource
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
            'user'            => User::make($this->user),
            'channel_name'    => $this->channel_name,
            'device_name'     => $this->device_name,
            'last_matched_at' => $this->last_matched_at ? Carbon::make($this->last_matched_at)->toIso8601String() : null,
            'paying'          => $this->paying,
            'min_amount'      => max($this->ca_min_amount, $this->uca_min_amount),
            'max_amount'      => min($this->ca_max_amount, $this->uca_max_amount ?? PHP_INT_MAX),
        ];
    }
}
