<?php

namespace App\Http\Resources\Admin;

use App\Model\MerchantThirdChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MerchantThirdChannels extends JsonResource
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
            'id'                      => $this->getKey(),
            'name'                    => $this->name,
            'username'                => $this->username,
            'include_self_providers'   => $this->include_self_providers,
            'thirdChannelsList' => $this->whenLoaded('thirdChannels', function () {
                return $this->thirdChannels->map(function (MerchantThirdChannel $merchantThirdChannel) {
                    $thirdChannel = $merchantThirdChannel->thirdChannel;
                    return [
                        'id' => $merchantThirdChannel->getKey(),
                        'thirdchannel_id' => $thirdChannel->id,
                        'name' => $thirdChannel->name,
                        'class' => $thirdChannel->class,
                        'channel_code' => $thirdChannel->channel->name,
                        'merchant_id'  => $thirdChannel->merchant_id,
                        'thirdChannel'        => $thirdChannel->name .'('. $thirdChannel->merchant_id . ')',
                        'deposit_fee_percent' => $merchantThirdChannel->deposit_fee_percent,
                        'withdraw_fee' => $merchantThirdChannel->withdraw_fee,
                        'daifu_fee_percent' => $merchantThirdChannel->daifu_fee_percent,
                        'daifu_min' => $merchantThirdChannel->daifu_min,
                        'daifu_max' => $merchantThirdChannel->daifu_max,
                        'deposit_min' => $merchantThirdChannel->deposit_min,
                        'deposit_max' => $merchantThirdChannel->deposit_max,
                    ];
                });
            }),
        ];
    }
}
