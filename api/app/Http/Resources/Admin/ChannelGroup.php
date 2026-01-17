<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChannelGroup extends JsonResource
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
            'id'                 => $this->getKey(),
            'name'               => $this->channel->name . ' ' . $this->amount_description,
            'fixed_amount'       => $this->fixed_amount,
            'amount_description' => $this->amount_description,
        ];
    }
}
