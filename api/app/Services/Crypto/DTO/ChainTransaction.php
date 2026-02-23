<?php

namespace App\Services\Crypto\DTO;

class ChainTransaction
{
    public function __construct(
        public readonly string $txHash,
        public readonly string $from,
        public readonly string $to,
        public readonly string $amount,
        public readonly int $timestamp,
        public readonly int $confirmations,
    ) {}
}
