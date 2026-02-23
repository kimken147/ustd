<?php

namespace App\DTOs;

use App\Models\Transaction;
use App\Utils\BankCardTransferObject;

class TransactionParams
{
    public function __construct(
        public readonly string $amount,
        public readonly ?BankCardTransferObject $bankCard = null,
        public readonly ?string $clientIpv4 = null,
        public readonly ?string $floatingAmount = null,
        public readonly ?string $note = null,
        public readonly ?string $notifyUrl = null,
        public readonly ?string $orderNumber = null,
        public readonly ?Transaction $parent = null,
        public readonly ?string $realName = null,
        public readonly ?string $subType = null,
        public readonly ?string $usdtRate = null,
        public readonly ?string $binanceUsdtRate = null,
        public readonly array $toData = [],
    ) {}
}
