<?php

namespace App\Http\Resources\Admin;
use Illuminate\Http\Resources\Json\JsonResource;

class Notification extends JsonResource
{

    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'mobile' => $this->mobile,
            'content' => $this->notification,
            'created_at' => $this->created_at
        ];
    }
}
