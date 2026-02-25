<?php

namespace Tests\Unit\Services\Withdraw;

use App\Models\FeatureToggle;
use App\Models\User;
use App\Models\Wallet;
use App\Repository\FeatureToggleRepository;
use App\Services\Withdraw\BaseWithdrawService;
use App\Services\Withdraw\DTO\WithdrawContext;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;
use App\Services\Withdraw\ThirdChannelDispatcher;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\TransactionFactory;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Http\Request;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class WithdrawValidationTest extends TestCase
{
    private BaseWithdrawService $service;
    private FeatureToggleRepository $featureToggleRepo;
    private BCMathUtil $bcMath;
    private FloatUtil $floatUtil;
    private BankCardTransferObject $bankCardTO;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureToggleRepo = Mockery::mock(FeatureToggleRepository::class);
        $this->bcMath = new BCMathUtil();
        $this->floatUtil = new FloatUtil();
        $this->bankCardTO = new BankCardTransferObject();

        $transactionFactory = Mockery::mock(TransactionFactory::class);
        $walletUtil = Mockery::mock(WalletUtil::class);
        $whitelistedIpManager = Mockery::mock(WhitelistedIpManager::class);
        $thirdChannelDispatcher = Mockery::mock(ThirdChannelDispatcher::class);

        // Use anonymous subclass to avoid DB-dependent abstract method implementations
        $this->service = new class(
            $this->featureToggleRepo,
            $this->bcMath,
            $this->floatUtil,
            $transactionFactory,
            $walletUtil,
            $whitelistedIpManager,
            $this->bankCardTO,
            $thirdChannelDispatcher,
        ) extends BaseWithdrawService {
            protected function getSubType(): string
            {
                return 'withdraw';
            }

            protected function getMinAmountField(): string
            {
                return 'withdraw_min_amount';
            }

            protected function getMaxAmountField(): string
            {
                return 'withdraw_max_amount';
            }

            protected function calculateTotalCost(WithdrawContext $context): string
            {
                // Simple stub: total cost equals the withdraw amount (no fee)
                return $context->amount;
            }

            protected function validateFeatureEnabled(WithdrawContext $context): void
            {
                // No-op for unit tests
            }

            protected function getPaufenEnabled(User $merchant): bool
            {
                return false;
            }
        };
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────

    private function callProtected(string $method, ...$args): mixed
    {
        $ref = new ReflectionMethod($this->service, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->service, ...$args);
    }

    private function makeContext(
        string $amount = '100.00',
        string $source = WithdrawContext::SOURCE_THIRD_PARTY,
        ?Wallet $wallet = null,
        ?User $merchant = null,
    ): WithdrawContext {
        $merchant = $merchant ?? $this->makeMerchant();
        $wallet = $wallet ?? $this->makeWallet();

        $bankCard = $this->bankCardTO->plain('工商银行', '6222000000000001', '张三', '广东省', '深圳市');

        return new WithdrawContext(
            merchant: $merchant,
            wallet: $wallet,
            amount: $amount,
            bankCard: $bankCard,
            orderNumber: 'ORD' . uniqid(),
            notifyUrl: null,
            source: $source,
        );
    }

    private function makeMerchant(): User
    {
        $merchant = Mockery::mock(User::class)->makePartial();
        $merchant->shouldReceive('getKey')->andReturn(1);
        $merchant->withdraw_enable = true;
        $merchant->paufen_withdraw_enable = false;

        return $merchant;
    }

    /**
     * Create a Wallet mock with proper balance attributes.
     *
     * Wallet::available_balance is a computed accessor (balance - frozen_balance),
     * so we set the underlying balance and frozen_balance attributes instead.
     */
    private function makeWallet(array $overrides = []): Wallet
    {
        $wallet = Mockery::mock(Wallet::class)->makePartial();

        $wallet->withdraw_min_amount = $overrides['withdraw_min_amount'] ?? '0';
        $wallet->withdraw_max_amount = $overrides['withdraw_max_amount'] ?? '0';

        // Set underlying attributes for the available_balance accessor
        $balance = $overrides['available_balance'] ?? '10000.00';
        $wallet->balance = $balance;
        $wallet->frozen_balance = '0.00';

        return $wallet;
    }

    // ──────────────────────────────────────────────────
    // validateAmount
    // ──────────────────────────────────────────────────

    public function test_validateAmount_rejects_below_minimum(): void
    {
        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false);

        $context = $this->makeContext(amount: '0.50');

        $this->expectException(WithdrawValidationException::class);

        $this->callProtected('validateAmount', $context);
    }

    public function test_validateAmount_accepts_valid_amount(): void
    {
        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false);

        $context = $this->makeContext(amount: '100.00');

        // Should not throw any exception
        $this->callProtected('validateAmount', $context);
        $this->assertTrue(true);
    }

    public function test_validateAmount_rejects_decimal_when_feature_enabled(): void
    {
        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::NO_FLOAT_IN_WITHDRAWS)
            ->andReturn(true);

        $context = $this->makeContext(amount: '100.50');

        $this->expectException(WithdrawValidationException::class);

        $this->callProtected('validateAmount', $context);
    }

    public function test_validateAmount_rejects_above_max(): void
    {
        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false);

        $wallet = $this->makeWallet(['withdraw_max_amount' => '500.00']);
        $context = $this->makeContext(amount: '600.00', wallet: $wallet);

        $this->expectException(WithdrawValidationException::class);

        $this->callProtected('validateAmount', $context);
    }

    // ──────────────────────────────────────────────────
    // validateRequiredAttributes
    // ──────────────────────────────────────────────────

    public function test_validateRequiredAttributes_throws_on_missing(): void
    {
        $request = Request::create('/api/withdraw', 'POST', [
            // 'username' is intentionally missing
            'amount' => '100',
            'bank_card_number' => '6222000000000001',
            'bank_name' => '工商银行',
            'order_number' => 'ORD123',
            'sign' => 'abc123',
        ]);

        $this->expectException(WithdrawValidationException::class);

        $this->callProtected('validateRequiredAttributes', $request);
    }

    public function test_validateRequiredAttributes_passes_when_complete(): void
    {
        $request = Request::create('/api/withdraw', 'POST', [
            'username' => 'merchant01',
            'amount' => '100',
            'bank_card_number' => '6222000000000001',
            'bank_name' => '工商银行',
            'order_number' => 'ORD123',
            'sign' => 'abc123',
        ]);

        // Should not throw any exception
        $this->callProtected('validateRequiredAttributes', $request);
        $this->assertTrue(true);
    }

    // ──────────────────────────────────────────────────
    // validateBalance
    // ──────────────────────────────────────────────────

    public function test_validateBalance_rejects_insufficient(): void
    {
        $wallet = $this->makeWallet(['available_balance' => '50.00']);
        $context = $this->makeContext(amount: '100.00', wallet: $wallet);

        $this->expectException(WithdrawValidationException::class);

        $this->callProtected('validateBalance', $context);
    }
}
