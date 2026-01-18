<?php

namespace App\Services\Withdraw\DTO;

use App\Models\Bank;
use App\Models\Channel;
use App\Models\User;
use App\Models\Wallet;
use App\Utils\BankCardTransferObject;

class WithdrawContext
{
    public const SOURCE_THIRD_PARTY = 'third_party';
    public const SOURCE_MERCHANT = 'merchant';

    public function __construct(
        public readonly User $merchant,
        public readonly Wallet $wallet,
        public readonly string $amount,
        public readonly BankCardTransferObject $bankCard,
        public readonly string $orderNumber,
        public readonly ?string $notifyUrl,
        public readonly string $source,
        public readonly ?string $usdtRate = null,
        public readonly ?string $binanceUsdtRate = null,
    ) {}

    public function isFromThirdParty(): bool
    {
        return $this->source === self::SOURCE_THIRD_PARTY;
    }

    public function isFromMerchant(): bool
    {
        return $this->source === self::SOURCE_MERCHANT;
    }

    public function isUsdt(): bool
    {
        return $this->bankCard->bankName === Channel::CODE_USDT;
    }

    public function getBank(): ?Bank
    {
        return Bank::where('name', $this->bankCard->bankName)
            ->orWhere('code', $this->bankCard->bankName)
            ->first();
    }
}
