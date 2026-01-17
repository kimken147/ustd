<?php

namespace App\Http\Resources\Exchange;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class User extends JsonResource
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
            'id'                 => $this->id,
            'last_login_ipv4'    => $this->last_login_ipv4,
            'role'               => $this->role,
            'status'             => $this->status,
            'agent_enable'       => $this->agent_enable,
            'google2fa_enable'   => $this->google2fa_enable,
            'ready_for_matching' => $this->ready_for_matching,
            'name'               => $this->name,
            'username'           => $this->username,
            'last_login_at'      => optional($this->last_login_at)->toIso8601String(),
            'wallet'             => Wallet::make($this->whenLoaded('wallet')),
        ];
    }
}
