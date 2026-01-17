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
        $data = [
            'system_order_number'   => $this->system_order_number,
            'order_number'          => $this->order_number,
            'status'                => $this->status,
            'amount'                => $this->amount,
            'fee'                   => $this->transactionFees->filter($this->filteredByUser($this->from))->first()->fee,
            'username'              => $this->from->username,
            'notify_url'            => $this->notify_url,
            'created_at'            => $this->created_at->toIso8601String(),
            'confirmed_at'          => optional($this->confirmed_at)->toIso8601String() ?? '',
        ];

        $data = array_merge($data, $this->from_channel_account);

        return $this->withSign($this->from, $data);
    }

    private function filteredByUser(User $user)
    {
        return function (TransactionFee $withdrawFee) use ($user) {
            return optional($withdrawFee->user)->is($user);
        };
    }
}
