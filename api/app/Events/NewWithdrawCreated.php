<?php

namespace App\Events;

use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewWithdrawCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $transactionId;
    public string $systemOrderNumber;
    public string $orderNumber;
    public string $merchantName;
    public string $amount;
    public string $bankName;
    public string $bankCardNumber;
    public string $bankCardHolderName;
    public int $subType;
    public string $createdAt;

    public function __construct(Transaction $transaction)
    {
        $this->transactionId = $transaction->id;
        $this->systemOrderNumber = $transaction->system_order_number ?? '';
        $this->orderNumber = $transaction->order_number ?? '';
        $this->merchantName = $transaction->from->username ?? '';
        $this->amount = $transaction->amount;
        $toAccount = $transaction->to_channel_account ?? [];
        $this->bankName = $toAccount['bank_name'] ?? '';
        $this->bankCardNumber = $toAccount['bank_card_number'] ?? '';
        $this->bankCardHolderName = $toAccount['bank_card_holder_name'] ?? '';
        $this->subType = $transaction->sub_type;
        $this->createdAt = $transaction->created_at->toDateTimeString();
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('admin.withdraws'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'withdraw.created';
    }

    public $broadcastQueue = 'hpq';
}
