<?php

namespace App\Http\Resources\ThirdParty;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class Transaction extends JsonResource
{
    use WithSign;

    /**
     * @var array
     */
    public $matchedInformation;

    public function withMatchedInformation($matchedInformation)
    {
        $this->matchedInformation = $matchedInformation;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     * @throws Throwable
     */
    public function toArray($request)
    {
        $data = [
            'trade_no'            => $this->system_order_number,
            'out_trade_no'        => $this->order_number,
            'status'              => \App\Models\Transaction::toMerchantStatus($this->status),
            'amount'              => $this->amount,
            'merchant_id'         => $this->to->username,
            'notify_url'          => $this->notify_url,
            'return_url'          => data_get($this->to_channel_account, 'return_url', ''),
            'created_at'          => $this->created_at->toIso8601String(),
            'confirmed_at'        => optional($this->confirmed_at)->toIso8601String() ?? '',
        ];

        return $this->withSign($this->to, array_merge($this->matchedInformation ?? [], $data));
    }
}
