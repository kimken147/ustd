<?php

namespace App\Http\Resources\ThirdParty;

use App\Models\Channel;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
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
            'system_order_number' => $this->system_order_number,
            'order_number'        => $this->order_number,
            'status'              => $this->status,
            'amount'              => $this->amount,
            'username'            => $this->to->username,
            'notify_url'          => $this->notify_url,
            'return_url'          => data_get($this->to_channel_account, 'return_url', ''),
            'created_at'          => $this->created_at->toIso8601String(),
            'confirmed_at'        => optional($this->confirmed_at)->toIso8601String() ?? '',
        ];

        if ($this->channel_code == Channel::CODE_USDT) {
            $data['usdt_rate'] = $this->usdt_rate;
            $data['rate_amount'] = $this->rateAmount;
        }

        return $this->withSign($this->to, array_merge($this->matchedInformation ?? [], $data));
    }
}
