<?php

namespace App\Services\Transaction\DTO;

use App\Models\UserChannelAccount;

class MatchedInfo
{
    public function __construct(
        public readonly ?string $receiverAccount = null,
        public readonly ?string $receiverName = null,
        public readonly ?string $receiverBankName = null,
        public readonly ?string $receiverBankBranch = null,
        public readonly ?string $bankCardNumber = null,
        public readonly ?string $bankCardHolderName = null,
        public readonly ?string $redirectUrl = null,
        public readonly ?string $note = null,
    ) {}

    public static function fromUserChannelAccount(array $fromChannelAccount): self
    {
        return new self(
            receiverAccount: data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_ACCOUNT),
            receiverName: data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME),
            receiverBankName: data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_NAME),
            receiverBankBranch: data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_BRANCH),
            bankCardNumber: data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER),
            redirectUrl: data_get($fromChannelAccount, UserChannelAccount::DETAIL_KEY_REDIRECT_URL),
        );
    }

    public static function fromThirdChannelResponse(array $data, ?string $note = null): self
    {
        return new self(
            receiverAccount: $data['receiver_account'] ?? null,
            receiverName: $data['receiver_name'] ?? null,
            receiverBankName: $data['receiver_bank_name'] ?? null,
            receiverBankBranch: $data['receiver_bank_branch'] ?? null,
            note: $note,
        );
    }
}
