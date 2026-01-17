<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use LogicException;
use RuntimeException;

class TransactionFee extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        if (!$this->relationLoaded('user')) {
            throw new LogicException('You should load user');
        }

        switch ($this->user->role) {
            case \App\Model\User::ROLE_PROVIDER:
                $userKeyName = 'provider';
                break;
            case \App\Model\User::ROLE_MERCHANT:
                $userKeyName = 'merchant';
                break;
            default:
                throw new RuntimeException();
        }

        return [
            $userKeyName    => User::make($this->user),
            'fee'           => $this->fee,
            'profit'        => $this->profit,
            'actual_fee'    => $this->actual_fee,
            'actual_profit' => $this->actual_profit,
        ];

    }
}
