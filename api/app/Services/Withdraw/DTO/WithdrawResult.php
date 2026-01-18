<?php

namespace App\Services\Withdraw\DTO;

use App\Models\Transaction;

class WithdrawResult
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly bool $success = true,
        public readonly ?string $message = null,
    ) {}

    public function getTransaction(): Transaction
    {
        return $this->transaction->refresh()->load([
            'from',
            'transactionFees',
            'fromChannelAccount',
        ]);
    }
}
