<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeLimitBank extends JsonResource
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
            'status'     => $this->status,
            'bank_name'  => $this->bank->name ?? $this->bank_name,
            'bank_id'    => $this->bank->id ?? 0,
            'started_at' => $this->started_at->format('H:i:s'),
            'ended_at'   => $this->ended_at->format('H:i:s'),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
