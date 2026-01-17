<?php

namespace App\Http\Resources\Merchant;

use App\Model\TransactionFee;
use App\Model\UserChannelAccount;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Utils\BCMathUtil;

class Transaction extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        $bcMath = app(BCMathUtil::class);

        $currentUserTransactionFee = optional(
            $this->transactionFees
                ->filter($this->filteredByUser(auth()->user()))
                ->first()
        );

        $toChannel = $this->to_channel_account;

        return [
            'id' => $this->getKey(),
            'system_order_number' => $this->system_order_number,
            'order_number' => $this->order_number,
            'channel_name' => optional($this->channel)->name,
            'channel_code' => $this->channel_code,
            'amount' => AmountDisplayTransformer::transform($this->amount),
            'fee' => $bcMath->max($currentUserTransactionFee->actual_fee, $currentUserTransactionFee->actual_profit),
            'merchant' => User::make($this->whenLoaded('to')),
            'merchant_fees' => TransactionFeeCollection::make($this->whenLoaded('transactionFees',
                function () {
                    return $this->transactionFees
                        ->filter($this->filteredByRole(\App\Model\User::ROLE_MERCHANT))
                        ->filter($this->filterByDescent());
                })),
            'status' => $this->status,
            'notify_status' => $this->notify_status,
            'real_name' => data_get($toChannel, UserChannelAccount::DETAIL_KEY_REAL_NAME),
            'created_at' => $this->created_at->toIso8601String(),
            'confirmed_at' => optional($this->confirmed_at)->toIso8601String(),
            'notified_at' => optional($this->notified_at)->toIso8601String(),
            'matched_at' => optional($this->matched_at)->toIso8601String(),
            'actual_amount' => AmountDisplayTransformer::transform($this->actual_amount),
            'floating_amount' => AmountDisplayTransformer::transform($this->floating_amount),
            'notify_url' => $this->notify_url,
            'client_ip' => $this->client_ipv4,
            'usdt_rate' => $this->usdt_rate,
            '_search1' => $this->_search1
        ];
    }

    private function filteredByUser(\App\Model\User $merchant)
    {
        return function (TransactionFee $transactionFee) use ($merchant) {
            return optional($transactionFee->user)->is($merchant);
        };
    }

    private function filteredByRole(int $role)
    {
        return function (TransactionFee $transactionFee) use ($role) {
            if ($role === \App\Model\User::ROLE_ADMIN) {
                return $transactionFee->user_id === 0;
            }

            return optional($transactionFee->user)->role === $role;
        };
    }

    private function filterByDescent()
    {
        return function (TransactionFee $transactionFee) {
            return in_array($transactionFee->user_id, \App\Model\User::descendantsAndSelf(auth()->id())->pluck('id')->all());
        };
    }
}
