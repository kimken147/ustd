<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ChainTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'tx_hash' => $this->tx_hash,
            'user_channel_account_id' => $this->user_channel_account_id,
            'user_channel_account' => $this->whenLoaded('userChannelAccount', fn () => [
                'id' => $this->userChannelAccount->id,
                'name' => $this->userChannelAccount->name,
                'account' => $this->userChannelAccount->account,
            ]),
            'direction' => $this->direction,
            'from_address' => $this->from_address,
            'to_address' => $this->to_address,
            'amount' => $this->amount,
            'token_type' => $this->token_type ?? 'USDT',
            'block_number' => $this->block_number,
            'block_timestamp' => $this->block_timestamp?->toISOString(),
            'confirmations' => $this->confirmations,
            'match_status' => $this->match_status,
            'matched_transaction_id' => $this->matched_transaction_id,
            'matched_transaction' => $this->whenLoaded('matchedTransaction', fn () => [
                'id' => $this->matchedTransaction->id,
                'order_number' => $this->matchedTransaction->order_number,
                'amount' => $this->matchedTransaction->amount,
                'status' => $this->matchedTransaction->status,
            ]),
            'matched_at' => $this->matched_at?->toISOString(),
            'matched_by' => $this->matched_by,
            'matched_by_user' => $this->whenLoaded('matchedByUser', fn () => [
                'id' => $this->matchedByUser->id,
                'name' => $this->matchedByUser->name,
            ]),
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
