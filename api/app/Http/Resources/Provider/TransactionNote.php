<?php

namespace App\Http\Resources\Provider;

use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\User;

class TransactionNote extends JsonResource
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
            'note'                  => $this->note,
            'created_at'            => $this->created_at->toIso8601String(),
        ];
    }
}
