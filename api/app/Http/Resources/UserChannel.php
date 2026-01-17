<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserChannel extends JsonResource
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
            'id'               => $this->id,
            'name'             => $this->channelGroup->channel->name.' '.$this->channelGroup->amount_description,
            'code'             => $this->channelGroup->channel->code,
            'amount_description' => $this->channelGroup->amount_description,
            'status'           => $this->status,
            'min_amount'       => $this->min_amount,
            'max_amount'       => $this->max_amount,
            'fee_percent'      => $this->fee_percent,
            'floating_enable'  => $this->floating_enable,
            'real_name_enable' => $this->real_name_enable,
            'deposit_account_fields' => $this->channelGroup->channel->deposit_account_fields
        ];
    }
}
