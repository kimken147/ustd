<?php

namespace App\Http\Resources\Merchant;

use App\Models\TransactionFee;
use App\Models\TransactionNote;
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
            'sub_type'              => $this->sub_type,
            'system_order_number'   => $this->system_order_number,
            'order_number'          => $this->order_number,
            'amount'                => AmountDisplayTransformer::transform($this->amount),
            'usdt'                  => bcdiv($this->amount,($this->usdt_rate > 0 ? $this->usdt_rate : 1),2),
            'fee'                   => $this->transactionFees
                ->filter($this->filteredByUser($this->from))
                ->first()
                ->fee,
            'merchant'              => User::make($this->whenLoaded('from')),
            'status'                => $this->status,
            'notify_status'         => $this->notify_status,
            'notes'                 => TransactionNoteCollection::make($this->transactionNotes),
            'bank_card_holder_name' => data_get($this->from_channel_account, 'bank_card_holder_name'),
            'bank_name'             => data_get($this->from_channel_account, 'bank_name'),
            'bank_province'         => data_get($this->from_channel_account, 'bank_province'),
            'bank_city'             => data_get($this->from_channel_account, 'bank_city'),
            'bank_card_number'      => data_get($this->from_channel_account, 'bank_card_number'),
            'created_at'            => $this->created_at->toIso8601String(),
            'confirmed_at'          => optional($this->confirmed_at)->toIso8601String(),
            'notified_at'           => optional($this->notified_at)->toIso8601String(),
            'notify_url'            => $this->notify_url,
            '_search1' => $this->_search1
        ];
    }

    private function filteredByUser(\App\Model\User $user)
    {
        return function (TransactionFee $withdrawFee) use ($user) {
            return optional($withdrawFee->user)->is($user);
        };
    }
}
