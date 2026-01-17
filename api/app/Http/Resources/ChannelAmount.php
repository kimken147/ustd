<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property \App\Model\Channel channel
 */
class ChannelAmount extends JsonResource
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
            'id'             => $this->getKey(),
            'name'           => $this->channel->name.' '.$this->amount_description,
            'present_result' => $this->channel->present_result,
            'channel_code'   => $this->channel->code,
            'deposit_account_fields' => $this->channel->deposit_account_fields,
            'withdraw_account_fields' => $this->channel->withdraw_account_fields,
        ];
    }
}
