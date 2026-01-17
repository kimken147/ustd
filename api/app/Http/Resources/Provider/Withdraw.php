<?php

namespace App\Http\Resources\Provider;

use App\Models\TransactionFee;
use App\Models\User;
use App\Models\Transaction;
use App\Http\Resources\User as UserResource;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class Withdraw extends JsonResource
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
            'system_order_number'   => $this->system_order_number,
            'amount'                => AmountDisplayTransformer::transform($this->amount),
            'fee'                   => $this->transactionFees
                ->filter($this->filteredByUser($this->from))
                ->first()
                ->fee,
            'status'                => $this->status,
            'provider'              => UserResource::make($this->whenLoaded('from')),
            'bank_card_holder_name' => data_get($this->from_channel_account, 'bank_card_holder_name'),
            'bank_name'             => data_get($this->from_channel_account, 'bank_name'),
            'bank_card_number'      => data_get($this->from_channel_account, 'bank_card_number'),
            'bank_province'         => data_get($this->from_channel_account, 'bank_province'),
            'bank_city'             => data_get($this->from_channel_account, 'bank_city'),
            'created_at'            => $this->created_at->toIso8601String(),
            'confirmed_at'          => optional($this->confirmed_at)->toIso8601String(),
            'type'                  => $this->sub_type == Transaction::SUB_TYPE_WITHDRAW_PROFIT ? 'profit' : 'balance'
        ];
    }

    private function filteredByUser(User $user)
    {
        return function (TransactionFee $withdrawFee) use ($user) {
            return optional($withdrawFee->user)->is($user);
        };
    }
}
