<?php

namespace App\Http\Resources\Admin;

use App\Model\User;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SystemBankCard extends JsonResource
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
            'status'                => $this->status,
            'balance'               => $this->balance,
            'balance_text'          => AmountDisplayTransformer::transform($this->balance),
            'bank_card_holder_name' => $this->bank_card_holder_name,
            'bank_card_number'      => $this->bank_card_number,
            'bank_name'             => $this->bank_name,
            'bank_province'         => $this->bank_province,
            'bank_city'             => $this->bank_city,
            "note" => $this->note,
            'created_at'            => $this->created_at->toIso8601String(),
            'updated_at'            => $this->updated_at->toIso8601String(),
            'published_at'          => optional($this->published_at)->toIso8601String(),
            'last_matched_at'       => optional($this->last_matched_at)->toIso8601String(),
            'users'                 => $this->users->map(function (User $user) {
                return [
                    'id'                => $user->getKey(),
                    'name'              => $user->name,
                    'share_descendants' => (bool) $user->pivot->share_descendants,
                ];
            })->toArray(),
        ];
    }
}
