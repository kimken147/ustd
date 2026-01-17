<?php

namespace App\Http\Resources\Exchange;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * @property Device|null device
 */
class UserChannelAccount extends JsonResource
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
            'id'                    => $this->getKey(),
            'bank_card_holder_name' => data_get($this->detail, \App\Model\UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME),
            'bank_card_number'      => data_get($this->detail, \App\Model\UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER),
            'bank_name'             => data_get($this->detail, \App\Model\UserChannelAccount::DETAIL_KEY_BANK_NAME),
            'bank_province'         => data_get($this->detail, \App\Model\UserChannelAccount::DETAIL_KEY_BANK_PROVINCE),
            'bank_city'             => data_get($this->detail, \App\Model\UserChannelAccount::DETAIL_KEY_BANK_CITY)
        ];
    }
}
