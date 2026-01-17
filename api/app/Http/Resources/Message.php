<?php

namespace App\Http\Resources;

use \Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Message extends JsonResource
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
            'id' => $this->id,
            'from' => $this->whenLoaded('from', function () {
                return $this->from->only('id', 'name', 'role');
            }),
            'to' => $this->whenLoaded('to', function () {
                return $this->to->only('id', 'name', 'role');
            }),
            'text' => $this->text,
            'file' => $this->detail,
            'time' => $this->created_at->toIso8601String(),
            'read_at' => optional($this->readed_at)->toIso8601String()
        ];
    }
}
