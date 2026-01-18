<?php

namespace App\Services\Transaction\DTO;

use App\Models\Transaction;

class CreateTransactionResult
{
    public function __construct(
        public readonly Transaction $transaction,
        public readonly string $status,
        public readonly ?string $cashierUrl = null,
        public readonly ?string $qrCodePath = null,
        public readonly ?MatchedInfo $matchedInfo = null,
    ) {}

    public static function matching(Transaction $transaction): self
    {
        return new self($transaction, 'matching');
    }

    public static function matched(
        Transaction $transaction,
        string $qrCodePath,
        MatchedInfo $matchedInfo
    ): self {
        return new self($transaction, 'matched', null, $qrCodePath, $matchedInfo);
    }

    public static function thirdPaying(
        Transaction $transaction,
        string $cashierUrl,
        ?MatchedInfo $matchedInfo = null
    ): self {
        return new self($transaction, 'third_paying', $cashierUrl, null, $matchedInfo);
    }

    public static function matchingTimedOut(Transaction $transaction): self
    {
        return new self($transaction, 'matching_timed_out');
    }

    public static function payingTimedOut(Transaction $transaction): self
    {
        return new self($transaction, 'paying_timed_out');
    }

    public static function success(Transaction $transaction): self
    {
        return new self($transaction, 'success');
    }

    public function isMatched(): bool
    {
        return in_array($this->status, ['matched', 'third_paying']);
    }

    public function needsRedirect(): bool
    {
        return $this->status === 'third_paying' && $this->cashierUrl !== null;
    }
}
