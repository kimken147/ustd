<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Channel extends JsonResource
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
            'code'                       => $this->code,
            'name'                       => $this->name,
            'status'                     => $this->status,
            'order_timeout'              => $this->order_timeout,
            'order_timeout_enable'       => $this->order_timeout_enable,
            'transaction_timeout'        => $this->transaction_timeout,
            'transaction_timeout_enable' => $this->transaction_timeout_enable,
            'floating'                   => $this->floating,
            'floating_enable'            => $this->floating_enable,
            'note_type'                  => $this->note_type,
            'note_enable'                => $this->note_enable,
            'channel_groups'             => ChannelGroupCollection::make($this->channelGroups),
            'real_name_enable'           => $this->real_name_enable,
            'deposit_account_fields'     => $this->deposit_account_fields,
            'withdraw_account_fields'    => $this->withdraw_account_fields,
        ];
    }
}
