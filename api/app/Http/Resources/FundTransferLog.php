<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class FundTransferLog extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'source_address' => $this->source_address,
            'target_address' => $this->target_address,
            'amount' => $this->amount,
            'chain_network' => $this->chain_network,
            'tx_hash' => $this->tx_hash,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'source_merchant' => $this->whenLoaded('sourceAccount', fn () => $this->sourceAccount?->user?->name),
            'target_merchant' => $this->whenLoaded('targetAccount', fn () => $this->targetAccount?->user?->name),
            'operator_name' => $this->whenLoaded('operator', fn () => $this->operator?->name),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
