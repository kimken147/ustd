<?php

namespace App\Http\Resources\ThirdParty;

use App\Models\TransactionFee;
use App\Models\User;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * @property Carbon created_at
 * @property array from_channel_account
 * @property string notify_url
 * @property string username
 * @property string amount
 * @property int status
 * @property string order_number
 * @property string system_order_number
 * @property User from
 */
class Withdraw extends JsonResource
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
        $fromChannelAccount = $this->from_channel_account ?? [];

        $data = [
            'trade_no'              => $this->system_order_number,
            'out_trade_no'          => $this->order_number,
            'status'                => \App\Models\Transaction::toMerchantStatus($this->status),
            'amount'                => $this->amount,
            'fee'                   => $this->transactionFees->filter($this->filteredByUser($this->from))->first()->fee,
            'merchant_id'           => $this->from->username,
            'callback_url'          => $this->notify_url,
            'pay_address'           => $fromChannelAccount['bank_card_number'] ?? '',
            'network'               => $fromChannelAccount['bank_name'] ?? '',
            'payee_name'            => $fromChannelAccount['bank_card_holder_name'] ?? '',
            'created_at'            => $this->created_at->toIso8601String(),
            'confirmed_at'          => optional($this->confirmed_at)->toIso8601String() ?? '',
        ];

        return $this->withSign($this->from, $data);
    }

    private function filteredByUser(User $user)
    {
        return function (TransactionFee $withdrawFee) use ($user) {
            return optional($withdrawFee->user)->is($user);
        };
    }
}
