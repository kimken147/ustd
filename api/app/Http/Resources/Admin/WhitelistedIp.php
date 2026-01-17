<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WhitelistedIp extends JsonResource
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
            'id'         => $this->getKey(),
            'user'       => $this->whenLoaded('user', function () {
                return [
                    'id'       => $this->user->getKey(),
                    'role'     => $this->user->role,
                    'name'     => $this->user->name,
                    'username' => $this->user->username,
                ];
            }),
            'ipv4'       => $this->ipv4,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
