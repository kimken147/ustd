<?php

namespace App\Http\Resources;

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
            'type'                       => $this->type,
            'order_timeout'              => $this->order_timeout,
            'order_timeout_enable'       => $this->order_timeout_enable,
            'transaction_timeout'        => $this->transaction_timeout,
            'transaction_timeout_enable' => $this->transaction_timeout_enable,
            'floating'                   => $this->floating,
            'floating_enable'            => $this->floating_enable,
            'present_result'             => $this->present_result,
            'deposit_account_fields'     => $this->deposit_account_fields,
            'withdraw_account_fields'     => $this->withdraw_account_fields,
            "third_exclusive_enable" => $this->third_exclusive_enable
        ];
    }
}
