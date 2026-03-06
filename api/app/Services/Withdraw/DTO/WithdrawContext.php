<?php

namespace App\Services\Withdraw\DTO;

use App\Models\Channel;
use App\Models\User;
use App\Models\Wallet;
use App\Utils\BankCardTransferObject;

class WithdrawContext
{
    public const SOURCE_THIRD_PARTY = 'third_party';
    public const SOURCE_MERCHANT = 'merchant';
    public const SOURCE_ADMIN = 'admin';

    public function __construct(
        public readonly User $merchant,
        public readonly Wallet $wallet,
        public readonly string $amount,
        public readonly BankCardTransferObject $bankCard,
        public readonly string $orderNumber,
        public readonly ?string $notifyUrl,
        public readonly string $source,
    ) {}

    public function isFromThirdParty(): bool
    {
        return $this->source === self::SOURCE_THIRD_PARTY;
    }

    public function isFromMerchant(): bool
    {
        return $this->source === self::SOURCE_MERCHANT;
    }

    public function isFromAdmin(): bool
    {
        return $this->source === self::SOURCE_ADMIN;
    }

    public function isUsdt(): bool
    {
        return $this->bankCard->bankName === Channel::CODE_USDT;
    }

}
