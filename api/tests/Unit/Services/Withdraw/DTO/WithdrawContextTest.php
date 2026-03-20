<?php

namespace Tests\Unit\Services\Withdraw\DTO;

use App\Models\Channel;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Withdraw\DTO\WithdrawContext;
use App\Utils\BankCardTransferObject;
use Mockery;
use Tests\TestCase;

class WithdrawContextTest extends TestCase
{
    private function makeContext(string $source, string $bankName = 'ICBC'): WithdrawContext
    {
        $merchant = Mockery::mock(User::class);
        $wallet = Mockery::mock(Wallet::class);

        $bankCard = new BankCardTransferObject();
        $bankCard->bankName = $bankName;
        $bankCard->bankCardNumber = '6222000000000001';
        $bankCard->bankCardHolderName = 'Test User';

        return new WithdrawContext(
            merchant: $merchant,
            wallet: $wallet,
            amount: '100.00',
            bankCard: $bankCard,
            orderNumber: 'ORD123456',
            notifyUrl: null,
            source: $source,
        );
    }

    // ---------------------------------------------------------------
    // isFromThirdParty()
    // ---------------------------------------------------------------

    public function test_is_from_third_party_returns_true(): void
    {
        $context = $this->makeContext(WithdrawContext::SOURCE_THIRD_PARTY);

        $this->assertTrue($context->isFromThirdParty());
    }

    public function test_is_from_third_party_returns_false(): void
    {
        $context = $this->makeContext(WithdrawContext::SOURCE_MERCHANT);

        $this->assertFalse($context->isFromThirdParty());
    }

    // ---------------------------------------------------------------
    // isFromMerchant()
    // ---------------------------------------------------------------

    public function test_is_from_merchant_returns_true(): void
    {
        $context = $this->makeContext(WithdrawContext::SOURCE_MERCHANT);

        $this->assertTrue($context->isFromMerchant());
    }

    // ---------------------------------------------------------------
    // isFromAdmin()
    // ---------------------------------------------------------------

    public function test_is_from_admin_returns_true(): void
    {
        $context = $this->makeContext(WithdrawContext::SOURCE_ADMIN);

        $this->assertTrue($context->isFromAdmin());
    }

    // ---------------------------------------------------------------
    // isUsdt()
    // ---------------------------------------------------------------

    public function test_is_usdt_returns_true(): void
    {
        $context = $this->makeContext(WithdrawContext::SOURCE_MERCHANT, Channel::CODE_USDT_TRC20);

        $this->assertTrue($context->isUsdt());
    }

    public function test_is_usdt_returns_false(): void
    {
        $context = $this->makeContext(WithdrawContext::SOURCE_MERCHANT, 'ICBC');

        $this->assertFalse($context->isUsdt());
    }
}
