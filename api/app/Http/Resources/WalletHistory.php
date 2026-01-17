<?php

namespace App\Http\Resources;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WalletHistory extends JsonResource
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
            'id'                    => $this->getKey(),
            'user'                  => $this->whenLoaded('user', function () {
                return [
                    'id'       => $this->user->getKey(),
                    'role'     => $this->user->role,
                    'name'     => $this->user->name,
                    'username' => $this->user->username,
                ];
            }),
            'operator'                  => $this->whenLoaded('operator', function () {
                return [
                    'id'       => $this->operator->getKey(),
                    'role'     => $this->operator->role,
                    'name'     => $this->operator->name,
                    'username' => $this->operator->username,
                ];
            }),
            'type'                  => $this->type,
            'balance_delta'         => AmountDisplayTransformer::transform(data_get($this->delta, 'balance', 0)),
            'profit_delta'          => AmountDisplayTransformer::transform(data_get($this->delta, 'profit', 0)),
            'frozen_balance_delta'  => AmountDisplayTransformer::transform(data_get($this->delta, 'frozen_balance', 0)),
            'balance_result'        => AmountDisplayTransformer::transform(data_get($this->result, 'balance', 0)),
            'profit_result'        => AmountDisplayTransformer::transform(data_get($this->result, 'profit', 0)),
            'frozen_balance_result' => AmountDisplayTransformer::transform(data_get($this->result, 'frozen_balance', 0)),
            'note'                  => $this->note,
            'created_at'            => $this->created_at->toIso8601String(),
        ];
    }
}
