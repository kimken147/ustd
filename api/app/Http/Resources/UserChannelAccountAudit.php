<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserChannelAccountAudit extends JsonResource
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'old_value' => $this->old_value,
            'new_value' => $this->new_value,
            'note' => $this->note,
            'updated_by_user' => $this->whenLoaded('updateByUser', function () {
                return $this->updateByUser->only(['id', 'name', 'username']);
            }),
            'updated_by_transaction' => $this->whenLoaded('updateByTransaction', function () {
                return $this->updateByTransaction->only(['id', 'system_order_number', 'order_number', '_serach1']);
            }),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}

