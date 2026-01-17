<?php

namespace App\Http\Resources\Provider;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberStatistics extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'name' => $this->name,
            'profit_amount' => $this->whenLoaded('walletHistories', function () {
                return AmountDisplayTransformer::transform($this->walletHistories->sum('delta.profit') ?? '0.00');
            }),
            'transaction_amount' => $this->whenLoaded('successPaufenTransactions', function () {
                return AmountDisplayTransformer::transform($this->successPaufenTransactions->sum('amount') ?? '0.00');
            }),
            'deposit_amount' => $this->whenLoaded('successDeposits', function () {
                return AmountDisplayTransformer::transform($this->successDeposits->sum('amount') ?? '0.00');
            }),
            'withdraw_amount' => $this->whenLoaded('successWithdraws', function () {
                return AmountDisplayTransformer::transform($this->successWithdraws->sum('amount') ?? '0.00');
            }),
        ];
    }
}
