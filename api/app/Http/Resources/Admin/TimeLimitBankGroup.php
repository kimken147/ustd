<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TimeLimitBankGroup extends JsonResource
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
            'bank_name'        => $this->bank->name ?? $this->bank_name,
            'bank_id'        => $this->bank->id ?? 0,
            'time_limit_banks' => TimeLimitBankCollection::make($this->timeLimitBanks),
        ];
    }
}
