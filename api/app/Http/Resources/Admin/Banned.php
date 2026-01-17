<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Banned extends JsonResource
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
            'id'                         => $this->id,
            'ipv4'                       => $this->ipv4,
            'realname'                   => $this->realname,
            'note'                       => $this->note,
            'created_at'                 => $this->created_at,
        ];
    }
}
