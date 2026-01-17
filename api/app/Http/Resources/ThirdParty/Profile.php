<?php

namespace App\Http\Resources\ThirdParty;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Throwable;

class Profile extends JsonResource
{

    use WithSign;

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     * @throws Throwable
     */
    public function toArray($request)
    {
        return $this->withSign($this, [
            'username'          => $this->username,
            'name'              => $this->name,
            'balance'           => $this->wallet->balance,
            'frozen_balance'    => $this->wallet->frozen_balance,
            'available_balance' => $this->wallet->available_balance,
        ]);
    }
}
