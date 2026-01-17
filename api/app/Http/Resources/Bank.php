<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Bank extends JsonResource
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
            'name'       => $this->name,
            'code'       => $this->code,
            'currency'   => $this->currency,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
