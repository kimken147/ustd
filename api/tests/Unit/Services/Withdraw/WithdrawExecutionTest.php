<?php

namespace Tests\Unit\Services\Withdraw;

use App\Models\Bank;
use App\Models\BankCard;
use App\Models\BannedRealname;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Repository\FeatureToggleRepository;
use App\Services\Withdraw\AgencyWithdrawService;
use App\Services\Withdraw\BaseWithdrawService;
use App\Services\Withdraw\DTO\WithdrawContext;
use App\Services\Withdraw\DTO\WithdrawResult;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;
use App\Services\Withdraw\ThirdChannelDispatcher;
use App\Services\Withdraw\WithdrawService;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\FloatUtil;
use App\Utils\SignatureCalculator;
use App\Utils\TransactionFactory;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class WithdrawExecutionTest extends TestCase
{
    use DatabaseTransactions;

    private FeatureToggleRepository $featureToggleRepo;
    private BCMathUtil $bcMath;
    private FloatUtil $floatUtil;
    private BankCardTransferObject $bankCardTO;
    private $transactionFactory;
    private $walletUtil;
    private $whitelistedIpManager;
    private $thirdChannelDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureToggleRepo = Mockery::mock(FeatureToggleRepository::class);
        $this->bcMath = new BCMathUtil();
        $this->floatUtil = new FloatUtil();
        $this->bankCardTO = new BankCardTransferObject();
        $this->transactionFactory = Mockery::mock(TransactionFactory::class);
        $this->walletUtil = Mockery::mock(WalletUtil::class);
        $this->whitelistedIpManager = Mockery::mock(WhitelistedIpManager::class);
        $this->thirdChannelDispatcher = Mockery::mock(ThirdChannelDispatcher::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────

    /**
     * Create an anonymous subclass of BaseWithdrawService for testing base class behavior.
     */
    private function makeBaseService(): BaseWithdrawService
    {
        return new class(
            $this->featureToggleRepo,
            $this->bcMath,
            $this->floatUtil,
            $this->transactionFactory,
            $this->walletUtil,
            $this->whitelistedIpManager,
            $this->bankCardTO,
            $this->thirdChannelDispatcher,
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
                return $context->amount;
            }

            protected function validateFeatureEnabled(WithdrawContext $context): void
            {
                // No-op for base class tests
            }

            protected function getPaufenEnabled(User $merchant): bool
            {
                return false;
            }
        };
    }

    private function makeWithdrawService(): WithdrawService
    {
        return new WithdrawService(
            $this->featureToggleRepo,
            $this->bcMath,
            $this->floatUtil,
            $this->transactionFactory,
            $this->walletUtil,
            $this->whitelistedIpManager,
            $this->bankCardTO,
            $this->thirdChannelDispatcher,
        );
    }

    private function makeAgencyWithdrawService(): AgencyWithdrawService
    {
        return new AgencyWithdrawService(
            $this->featureToggleRepo,
            $this->bcMath,
            $this->floatUtil,
            $this->transactionFactory,
            $this->walletUtil,
            $this->whitelistedIpManager,
            $this->bankCardTO,
            $this->thirdChannelDispatcher,
        );
    }

    private function callProtected(object $service, string $method, ...$args): mixed
    {
        $ref = new ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invoke($service, ...$args);
    }

    /**
     * Create a real merchant user in the database.
     */
    private function createMerchantInDb(array $overrides = []): User
    {
        $defaults = [
            'name' => 'TestMerchant',
            'username' => 'MERCHANT' . mt_rand(100000, 999999),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'role' => User::ROLE_MERCHANT,
            'status' => User::STATUS_ENABLE,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
            'withdraw_enable' => true,
            'agency_withdraw_enable' => true,
            'paufen_withdraw_enable' => false,
            'paufen_agency_withdraw_enable' => false,
            'transaction_enable' => true,
            'deposit_enable' => true,
            'balance_limit' => 0,
            'secret_key' => 'test_secret_key_' . mt_rand(100000, 999999),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
        ];

        $id = DB::table('users')->insertGetId(array_merge($defaults, $overrides));

        return User::find($id);
    }

    /**
     * Create a real wallet in the database.
     */
    private function createWalletInDb(int $userId, array $overrides = []): Wallet
    {
        $defaults = [
            'user_id' => $userId,
            'status' => Wallet::STATUS_ENABLE,
            'balance' => '10000.00',
            'frozen_balance' => '0.00',
            'withdraw_fee' => '0.00',
            'withdraw_profit_fee' => '0.00',
            'agency_withdraw_fee' => '0.00',
            'agency_withdraw_fee_dollar' => '0.00',
            'withdraw_min_amount' => '0',
            'withdraw_max_amount' => '0',
            'withdraw_profit_min_amount' => '0',
            'withdraw_profit_max_amount' => '0',
            'agency_withdraw_min_amount' => '0',
            'agency_withdraw_max_amount' => '0',
        ];

        $id = DB::table('wallets')->insertGetId(array_merge($defaults, $overrides));

        return Wallet::find($id);
    }

    /**
     * Build a WithdrawContext with real DB-backed models.
     */
    private function makeDbContext(
        User $merchant,
        Wallet $wallet,
        string $amount = '100.00',
        string $source = WithdrawContext::SOURCE_THIRD_PARTY,
        ?string $holderName = '张三',
        ?string $orderNumber = null,
    ): WithdrawContext {
        $bankCard = $this->bankCardTO->plain('工商银行', '6222000000000001', $holderName ?? '张三', '广东省', '深圳市');

        return new WithdrawContext(
            merchant: $merchant,
            wallet: $wallet,
            amount: $amount,
            bankCard: $bankCard,
            orderNumber: $orderNumber ?? ('ORD' . uniqid()),
            notifyUrl: 'https://example.com/notify',
            source: $source,
        );
    }

    /**
     * Create a mock Wallet for non-DB tests.
     */
    private function makeMockWallet(array $overrides = []): Wallet
    {
        $wallet = Mockery::mock(Wallet::class)->makePartial();
        $wallet->withdraw_min_amount = $overrides['withdraw_min_amount'] ?? '0';
        $wallet->withdraw_max_amount = $overrides['withdraw_max_amount'] ?? '0';
        $wallet->agency_withdraw_min_amount = $overrides['agency_withdraw_min_amount'] ?? '0';
        $wallet->agency_withdraw_max_amount = $overrides['agency_withdraw_max_amount'] ?? '0';
        $wallet->balance = $overrides['balance'] ?? '10000.00';
        $wallet->frozen_balance = $overrides['frozen_balance'] ?? '0.00';

        return $wallet;
    }

    /**
     * Create a mock User for non-DB tests.
     */
    private function makeMockMerchant(array $overrides = []): User
    {
        $merchant = Mockery::mock(User::class)->makePartial();
        $merchant->shouldReceive('getKey')->andReturn($overrides['id'] ?? 1);
        $merchant->withdraw_enable = $overrides['withdraw_enable'] ?? true;
        $merchant->agency_withdraw_enable = $overrides['agency_withdraw_enable'] ?? true;
        $merchant->paufen_withdraw_enable = $overrides['paufen_withdraw_enable'] ?? false;
        $merchant->paufen_agency_withdraw_enable = $overrides['paufen_agency_withdraw_enable'] ?? false;
        $merchant->role = $overrides['role'] ?? User::ROLE_MERCHANT;

        return $merchant;
    }

    // ================================================================
    // BaseWithdrawService.execute() — 5 tests
    // ================================================================

    public function test_execute_happy_path_creates_transaction_and_withdraws(): void
    {
        $merchant = $this->createMerchantInDb();
        $wallet = $this->createWalletInDb($merchant->id, ['balance' => '10000.00']);

        $context = $this->makeDbContext($merchant, $wallet, '100.00');

        // Feature toggle: no float restriction, no bank mapping
        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false);

        // ThirdChannelDispatcher returns a real transaction
        $transaction = new Transaction();
        $transaction->id = 999;
        $transaction->order_number = $context->orderNumber;

        $this->thirdChannelDispatcher
            ->shouldReceive('dispatch')
            ->once()
            ->andReturn($transaction);

        $this->walletUtil
            ->shouldReceive('withdraw')
            ->once()
            ->with($wallet, '100.00', $context->orderNumber, 'withdraw');

        $service = $this->makeBaseService();
        $result = $service->execute($context);

        $this->assertInstanceOf(WithdrawResult::class, $result);
        $this->assertSame($transaction, $result->transaction);
    }

    public function test_execute_fails_when_balance_insufficient(): void
    {
        $merchant = $this->createMerchantInDb();
        $wallet = $this->createWalletInDb($merchant->id, ['balance' => '50.00']);

        $context = $this->makeDbContext($merchant, $wallet, '100.00');

        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false);

        $service = $this->makeBaseService();

        $this->expectException(WithdrawValidationException::class);

        $service->execute($context);
    }

    public function test_execute_fails_when_amount_below_minimum(): void
    {
        $merchant = $this->createMerchantInDb();
        $wallet = $this->createWalletInDb($merchant->id);

        $context = $this->makeDbContext($merchant, $wallet, '0.50');

        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false);

        $service = $this->makeBaseService();

        $this->expectException(WithdrawValidationException::class);

        $service->execute($context);
    }

    public function test_execute_fails_when_duplicate_order(): void
    {
        $merchant = $this->createMerchantInDb();
        $wallet = $this->createWalletInDb($merchant->id, ['balance' => '10000.00']);

        $orderNumber = 'DUPLICATE_ORD_' . uniqid();

        // Create an existing transaction with the same order_number and from_id
        DB::table('transactions')->insert([
            'from_id' => $merchant->id,
            'from_account_mode' => User::ACCOUNT_MODE_GENERAL,
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_PENDING_REVIEW,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'actual_amount' => '0.00',
            'order_number' => $orderNumber,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $context = $this->makeDbContext($merchant, $wallet, '100.00', WithdrawContext::SOURCE_THIRD_PARTY, '张三', $orderNumber);

        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false);

        $service = $this->makeBaseService();

        $this->expectException(WithdrawValidationException::class);

        $service->execute($context);
    }

    public function test_execute_fails_when_banned_realname(): void
    {
        $merchant = $this->createMerchantInDb();
        $wallet = $this->createWalletInDb($merchant->id, ['balance' => '10000.00']);

        $bannedName = '李四_BANNED_' . mt_rand(100000, 999999);

        // Create a banned realname record
        BannedRealname::create([
            'realname' => $bannedName,
            'type' => BannedRealname::TYPE_WITHDRAW,
        ]);

        $context = $this->makeDbContext($merchant, $wallet, '100.00', WithdrawContext::SOURCE_THIRD_PARTY, $bannedName);

        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false);

        $service = $this->makeBaseService();

        $this->expectException(WithdrawValidationException::class);

        $service->execute($context);
    }

    // ================================================================
    // BaseWithdrawService.buildContextFromThirdParty() — 3 tests
    // ================================================================

    public function test_buildContextFromThirdParty_happy_path(): void
    {
        $secretKey = 'secret_key_' . mt_rand(100000, 999999);
        $merchant = $this->createMerchantInDb(['secret_key' => $secretKey]);
        $this->createWalletInDb($merchant->id, ['balance' => '10000.00']);

        $parameters = [
            'username' => $merchant->username,
            'amount' => '500.00',
            'bank_card_number' => '6222000000000001',
            'bank_name' => '工商银行',
            'bank_card_holder_name' => '张三',
            'order_number' => 'TP_ORD_' . uniqid(),
            'notify_url' => 'https://example.com/notify',
        ];

        $sign = SignatureCalculator::calculate($parameters, $secretKey);
        $parameters['sign'] = $sign;

        $request = Request::create('/api/withdraw', 'POST', $parameters);

        $this->whitelistedIpManager
            ->shouldReceive('isNotAllowedToUseThirdPartyApi')
            ->once()
            ->andReturn(false);

        $service = $this->makeBaseService();
        $context = $service->buildContextFromThirdParty($request);

        $this->assertInstanceOf(WithdrawContext::class, $context);
        $this->assertEquals($merchant->id, $context->merchant->id);
        $this->assertEquals('500.00', $context->amount);
        $this->assertEquals('工商银行', $context->bankCard->bankName);
        $this->assertEquals('6222000000000001', $context->bankCard->bankCardNumber);
        $this->assertEquals('张三', $context->bankCard->bankCardHolderName);
        $this->assertTrue($context->isFromThirdParty());
        $this->assertFalse($context->isFromMerchant());
    }

    public function test_buildContextFromThirdParty_fails_when_merchant_not_found(): void
    {
        $parameters = [
            'username' => 'NONEXISTENT_USER_' . mt_rand(100000, 999999),
            'amount' => '500.00',
            'bank_card_number' => '6222000000000001',
            'bank_name' => '工商银行',
            'order_number' => 'TP_ORD_' . uniqid(),
            'sign' => 'any_sign',
        ];

        $request = Request::create('/api/withdraw', 'POST', $parameters);

        $service = $this->makeBaseService();

        $this->expectException(WithdrawValidationException::class);

        $service->buildContextFromThirdParty($request);
    }

    public function test_buildContextFromThirdParty_fails_when_signature_invalid(): void
    {
        $merchant = $this->createMerchantInDb();
        $this->createWalletInDb($merchant->id);

        $parameters = [
            'username' => $merchant->username,
            'amount' => '500.00',
            'bank_card_number' => '6222000000000001',
            'bank_name' => '工商银行',
            'order_number' => 'TP_ORD_' . uniqid(),
            'sign' => 'invalid_signature_value',
        ];

        $request = Request::create('/api/withdraw', 'POST', $parameters);

        $service = $this->makeBaseService();

        $this->expectException(WithdrawValidationException::class);

        $service->buildContextFromThirdParty($request);
    }

    // ================================================================
    // BaseWithdrawService.buildContextFromMerchant() — 2 tests
    // ================================================================

    public function test_buildContextFromMerchant_happy_path(): void
    {
        $merchant = $this->createMerchantInDb([
            'withdraw_google2fa_enable' => false,
        ]);
        $wallet = $this->createWalletInDb($merchant->id, ['balance' => '10000.00']);

        $request = Request::create('/api/withdraw', 'POST', [
            'amount' => '200.00',
            'bank_name' => '建设银行',
            'bank_card_number' => '6217000000000002',
            'bank_card_holder_name' => '王五',
            'bank_province' => '北京市',
            'bank_city' => '北京市',
        ]);

        $service = $this->makeBaseService();
        $context = $service->buildContextFromMerchant($request, $merchant);

        $this->assertInstanceOf(WithdrawContext::class, $context);
        $this->assertEquals($merchant->id, $context->merchant->id);
        $this->assertEquals('200.00', $context->amount);
        $this->assertTrue($context->isFromMerchant());
        $this->assertFalse($context->isFromThirdParty());
        $this->assertNull($context->notifyUrl);
    }

    public function test_buildContextFromMerchant_fails_when_not_merchant(): void
    {
        $admin = $this->createMerchantInDb(['role' => User::ROLE_ADMIN]);
        $this->createWalletInDb($admin->id);

        $request = Request::create('/api/withdraw', 'POST', [
            'amount' => '200.00',
            'bank_name' => '建设银行',
            'bank_card_number' => '6217000000000002',
        ]);

        $service = $this->makeBaseService();

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $service->buildContextFromMerchant($request, $admin);
    }

    // ================================================================
    // WithdrawService specific — 4 tests
    // ================================================================

    public function test_withdrawService_calculateTotalCost_delegates_to_wallet(): void
    {
        $wallet = $this->makeMockWallet();
        $wallet->shouldReceive('calculateTotalWithdrawAmount')
            ->with('500.00', false)
            ->once()
            ->andReturn('505.00');

        $merchant = $this->makeMockMerchant();
        $bankCard = $this->bankCardTO->plain('工商银行', '6222000000000001', '张三', '广东省', '深圳市');

        // Use anonymous subclass to override getBank() and avoid DB query on banks table
        $context = new class(
            $merchant, $wallet, '500.00', $bankCard, 'ORD' . uniqid(), null, WithdrawContext::SOURCE_THIRD_PARTY
        ) extends WithdrawContext {
            public function getBank(): ?Bank
            {
                return null;
            }
        };

        $service = $this->makeWithdrawService();
        $result = $this->callProtected($service, 'calculateTotalCost', $context);

        $this->assertEquals('505.00', $result);
    }

    public function test_withdrawService_validateFeatureEnabled_throws_when_disabled(): void
    {
        $merchant = $this->makeMockMerchant(['withdraw_enable' => false]);
        $wallet = $this->makeMockWallet();

        $bankCard = $this->bankCardTO->plain('工商银行', '6222000000000001', '张三', '广东省', '深圳市');

        $context = new WithdrawContext(
            merchant: $merchant,
            wallet: $wallet,
            amount: '100.00',
            bankCard: $bankCard,
            orderNumber: 'ORD' . uniqid(),
            notifyUrl: null,
            source: WithdrawContext::SOURCE_THIRD_PARTY,
        );

        $service = $this->makeWithdrawService();

        $this->expectException(WithdrawValidationException::class);

        $this->callProtected($service, 'validateFeatureEnabled', $context);
    }

    public function test_withdrawService_getPaufenEnabled_true(): void
    {
        $merchant = $this->makeMockMerchant(['paufen_withdraw_enable' => true]);

        $this->featureToggleRepo
            ->shouldReceive('enabled')
            ->with(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)
            ->once()
            ->andReturn(true);

        $service = $this->makeWithdrawService();
        $result = $this->callProtected($service, 'getPaufenEnabled', $merchant);

        $this->assertTrue($result);
    }

    public function test_withdrawService_buildContextFromMerchantWithBankCard_happy_path(): void
    {
        $merchant = $this->createMerchantInDb([
            'withdraw_google2fa_enable' => false,
        ]);
        $wallet = $this->createWalletInDb($merchant->id, ['balance' => '10000.00']);

        $bankCardId = DB::table('bank_cards')->insertGetId([
            'user_id' => $merchant->id,
            'status' => BankCard::STATUS_REVIEW_PASSED,
            'bank_card_holder_name' => '赵六',
            'bank_card_number' => '6217000000000003',
            'bank_name' => '农业银行',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $request = Request::create('/api/withdraw', 'POST', [
            'amount' => '300.00',
            'bank_card_id' => $bankCardId,
        ]);

        $service = $this->makeWithdrawService();
        $context = $service->buildContextFromMerchantWithBankCard($request, $merchant);

        $this->assertInstanceOf(WithdrawContext::class, $context);
        $this->assertEquals($merchant->id, $context->merchant->id);
        $this->assertEquals('300.00', $context->amount);
        $this->assertEquals('农业银行', $context->bankCard->bankName);
        $this->assertEquals('6217000000000003', $context->bankCard->bankCardNumber);
        $this->assertEquals('赵六', $context->bankCard->bankCardHolderName);
        $this->assertTrue($context->isFromMerchant());
    }

    // ================================================================
    // AgencyWithdrawService specific — 4 tests
    // ================================================================

    public function test_agencyWithdrawService_calculateTotalCost_delegates_to_wallet(): void
    {
        $wallet = $this->makeMockWallet();
        $wallet->shouldReceive('calculateTotalAgencyWithdrawAmount')
            ->with('800.00', false)
            ->once()
            ->andReturn('810.00');

        $merchant = $this->makeMockMerchant();
        $bankCard = $this->bankCardTO->plain('工商银行', '6222000000000001', '张三', '广东省', '深圳市');

        // Use anonymous subclass to override getBank() and avoid DB query on banks table
        $context = new class(
            $merchant, $wallet, '800.00', $bankCard, 'ORD' . uniqid(), null, WithdrawContext::SOURCE_THIRD_PARTY
        ) extends WithdrawContext {
            public function getBank(): ?Bank
            {
                return null;
            }
        };

        $service = $this->makeAgencyWithdrawService();
        $result = $this->callProtected($service, 'calculateTotalCost', $context);

        $this->assertEquals('810.00', $result);
    }

    public function test_agencyWithdrawService_validateFeatureEnabled_throws_when_feature_disabled(): void
    {
        $merchant = $this->makeMockMerchant(['agency_withdraw_enable' => true]);
        $wallet = $this->makeMockWallet();

        $bankCard = $this->bankCardTO->plain('工商银行', '6222000000000001', '张三', '广东省', '深圳市');

        $context = new WithdrawContext(
            merchant: $merchant,
            wallet: $wallet,
            amount: '100.00',
            bankCard: $bankCard,
            orderNumber: 'ORD' . uniqid(),
            notifyUrl: null,
            source: WithdrawContext::SOURCE_THIRD_PARTY,
        );

        $this->featureToggleRepo
            ->shouldReceive('enabled')
            ->with(FeatureToggle::ENABLE_AGENCY_WITHDRAW)
            ->once()
            ->andReturn(false);

        $service = $this->makeAgencyWithdrawService();

        $this->expectException(WithdrawValidationException::class);

        $this->callProtected($service, 'validateFeatureEnabled', $context);
    }

    public function test_agencyWithdrawService_validateFeatureEnabled_throws_when_merchant_disabled(): void
    {
        $merchant = $this->makeMockMerchant(['agency_withdraw_enable' => false]);
        $wallet = $this->makeMockWallet();

        $bankCard = $this->bankCardTO->plain('工商银行', '6222000000000001', '张三', '广东省', '深圳市');

        $context = new WithdrawContext(
            merchant: $merchant,
            wallet: $wallet,
            amount: '100.00',
            bankCard: $bankCard,
            orderNumber: 'ORD' . uniqid(),
            notifyUrl: null,
            source: WithdrawContext::SOURCE_THIRD_PARTY,
        );

        $this->featureToggleRepo
            ->shouldReceive('enabled')
            ->with(FeatureToggle::ENABLE_AGENCY_WITHDRAW)
            ->once()
            ->andReturn(true);

        $service = $this->makeAgencyWithdrawService();

        $this->expectException(WithdrawValidationException::class);

        $this->callProtected($service, 'validateFeatureEnabled', $context);
    }

    public function test_agencyWithdrawService_getPaufenEnabled_true(): void
    {
        $merchant = $this->makeMockMerchant(['paufen_agency_withdraw_enable' => true]);

        $this->featureToggleRepo
            ->shouldReceive('enabled')
            ->with(FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT)
            ->once()
            ->andReturn(true);

        $service = $this->makeAgencyWithdrawService();
        $result = $this->callProtected($service, 'getPaufenEnabled', $merchant);

        $this->assertTrue($result);
    }
}
