<?php

namespace App\Http\Resources\Admin;

use App\Model\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @method int getKey()
 * @property User user
 * @property string name
 * @property Carbon|null last_login_at
 * @property string last_login_ipv4
 * @property Carbon|null last_heartbeat_at
 * @property boolean regular_customer_first
 */
class Device extends JsonResource
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
            'id'                     => $this->getKey(),
            'regular_customer_first' => $this->regular_customer_first,
            'user'                   => \App\Http\Resources\User::make($this->user),
            'name'                   => $this->name,
            'last_login_at'          => optional($this->last_login_at)->toIso8601String(),
            'last_login_ipv4'        => $this->last_login_ipv4,
            'last_heartbeat_at'      => optional($this->last_heartbeat_at)->toIso8601String(),
        ];
    }
}
