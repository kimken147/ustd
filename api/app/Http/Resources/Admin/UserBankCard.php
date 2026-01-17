<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Resources\Json\JsonResource;

class UserBankCard extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id'                    => $this->getKey(),
            'status'                => $this->status,
            'user'                  => [
                'id'              => $this->user->getKey(),
                'role'            => $this->user->role,
                'name'            => $this->user->name,
                'username'        => $this->user->username,
                'last_login_at'   => optional($this->user->last_login_at)->toIso8601String(),
                'last_login_ipv4' => $this->user->last_login_ipv4,
            ],
            'bank_card_holder_name' => $this->bank_card_holder_name,
            'bank_card_number'      => $this->bank_card_number,
            'bank_name'             => $this->bank_name,
            'bank_province'         => $this->bank_province,
            'bank_city'             => $this->bank_city,
            'created_at'            => $this->created_at->toIso8601String(),
        ];
    }
}
