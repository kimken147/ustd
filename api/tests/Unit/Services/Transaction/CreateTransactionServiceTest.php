<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\Channel;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannel;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\AccountMatchingQueryBuilder;
use App\Services\Transaction\CreateTransactionService;
use App\Services\Transaction\DTO\CallbackResult;
use App\Services\Transaction\DTO\CreateTransactionContext;
use App\Services\Transaction\DTO\CreateTransactionResult;
use App\Services\Transaction\DTO\DemoContext;
use App\Services\Transaction\DTO\DemoResult;
use App\Services\Transaction\Exceptions\TransactionValidationException;
use App\Services\Transaction\TransactionFeeService;
use App\Services\Transaction\TransactionStatusService;
use App\Services\Transaction\TransactionValidationService;
use App\Utils\BCMathUtil;
use App\Utils\TransactionFactory;
use App\Utils\TransactionMutator;
use App\Utils\TransactionNoteUtil;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class CreateTransactionServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CreateTransactionService $service;
    private $bcMath;
    private $featureToggleRepository;
    private $transactionNoteUtil;
    private $transactionFactory;
    private $transactionMutator;
    private $transactionFeeService;
    private $walletUtil;
    private $whitelistedIpManager;
    private $accountMatchingQueryBuilder;
    private $validationService;
    private $statusService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bcMath = Mockery::mock(BCMathUtil::class);
        $this->featureToggleRepository = Mockery::mock(FeatureToggleRepository::class);
        $this->transactionNoteUtil = Mockery::mock(TransactionNoteUtil::class);
        $this->transactionFactory = Mockery::mock(TransactionFactory::class);
        $this->transactionMutator = Mockery::mock(TransactionMutator::class);
        $this->transactionFeeService = Mockery::mock(TransactionFeeService::class);
        $this->walletUtil = Mockery::mock(WalletUtil::class);
        $this->whitelistedIpManager = Mockery::mock(WhitelistedIpManager::class);
        $this->accountMatchingQueryBuilder = Mockery::mock(AccountMatchingQueryBuilder::class);
        $this->validationService = Mockery::mock(TransactionValidationService::class);
        $this->statusService = Mockery::mock(TransactionStatusService::class);

        $this->service = new CreateTransactionService(
            $this->bcMath,
            $this->featureToggleRepository,
            $this->transactionNoteUtil,
            $this->transactionFactory,
            $this->transactionMutator,
            $this->transactionFeeService,
            $this->walletUtil,
            $this->whitelistedIpManager,
            $this->accountMatchingQueryBuilder,
            $this->validationService,
            $this->statusService,
        );
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): int
    {
        $defaults = [
            'name' => 'TestUser',
            'username' => 'TESTUSER' . mt_rand(100000, 999999),
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
            'role' => User::ROLE_MERCHANT,
            'status' => User::STATUS_ENABLE,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
            'transaction_enable' => true,
            'balance_limit' => 0,
            'secret_key' => 'test_secret_key_' . mt_rand(100000, 999999),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
        ];

        return DB::table('users')->insertGetId(array_merge($defaults, $overrides));
    }

    private function createWallet(int $userId, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $userId,
            'status' => 1,
            'balance' => '1000.00',
            'profit' => '0.00',
            'frozen_balance' => '0.00',
        ];

        DB::table('wallets')->insert(array_merge($defaults, $overrides));
    }

    private function createMerchantWithWallet(array $overrides = []): User
    {
        $userId = $this->createUser(array_merge(['role' => User::ROLE_MERCHANT], $overrides));
        $this->createWallet($userId);

        return User::find($userId);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $defaults = [
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'from_channel_account' => json_encode([]),
            'to_channel_account' => json_encode([]),
            'amount' => '100.000000',
            'floating_amount' => '100.000000',
            'actual_amount' => '0',
            'order_number' => 'ORD' . mt_rand(100000, 999999),
            'system_order_number' => 'SYS' . mt_rand(100000, 999999),
        ];

        $id = DB::table('transactions')->insertGetId(array_merge($defaults, $overrides));

        return Transaction::find($id);
    }

    private function createChannel(array $overrides = []): Channel
    {
        $defaults = [
            'code' => 'TEST_CH_' . mt_rand(1000, 9999),
            'name' => 'Test Channel',
            'status' => Channel::STATUS_ENABLE,
            'order_timeout' => 300,
            'order_timeout_enable' => true,
            'transaction_timeout' => 600,
            'transaction_timeout_enable' => true,
            'floating' => '0',
            'floating_enable' => false,
            'present_result' => Channel::RESPONSE_QRCODE,
            'real_name_enable' => false,
            'note_enable' => false,
        ];

        DB::table('channels')->insert(array_merge($defaults, $overrides));

        return Channel::find($overrides['code'] ?? $defaults['code']);
    }

    private function makeContext(array $overrides = []): CreateTransactionContext
    {
        $defaults = [
            'channelCode' => 'TEST_CH',
            'username' => 'TESTMERCHANT',
            'amount' => '100',
            'orderNumber' => 'ORD123456',
            'notifyUrl' => 'https://example.com/notify',
            'sign' => 'test_sign',
        ];

        $params = array_merge($defaults, $overrides);

        return new CreateTransactionContext(
            channelCode: $params['channelCode'],
            username: $params['username'],
            amount: $params['amount'],
            orderNumber: $params['orderNumber'],
            notifyUrl: $params['notifyUrl'],
            sign: $params['sign'],
            clientIp: $params['clientIp'] ?? null,
            realName: $params['realName'] ?? null,
            returnUrl: $params['returnUrl'] ?? null,
            bankName: $params['bankName'] ?? null,
            usdtRate: $params['usdtRate'] ?? null,
            matchLastAccount: $params['matchLastAccount'] ?? null,
            isThirdParty: $params['isThirdParty'] ?? false,
        );
    }

    private function mockValidationServiceForCreate(User $merchant, Channel $channel, UserChannel $userChannel): void
    {
        $this->validationService
            ->shouldReceive('validateAndGetChannel')
            ->once()
            ->andReturn($channel);

        $this->validationService
            ->shouldReceive('validateAndGetMerchant')
            ->once()
            ->andReturn($merchant);

        $this->validationService
            ->shouldReceive('validateAndGetUserChannel')
            ->once()
            ->andReturn([$userChannel, null]);
    }

    // ===============================================================
    //  create() — Status routing
    // ===============================================================

    public function test_create_returns_early_when_transaction_is_paying(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $channel = $this->createChannel();
        $orderNumber = 'ORD_PAY_' . mt_rand(100000, 999999);

        $transaction = $this->createTransaction([
            'to_id' => $merchant->id,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'order_number' => $orderNumber,
            'channel_code' => $channel->code,
            'from_channel_account' => json_encode([]),
        ]);

        $userChannel = Mockery::mock(UserChannel::class);

        $context = $this->makeContext([
            'channelCode' => $channel->code,
            'username' => $merchant->username,
            'orderNumber' => $orderNumber,
        ]);

        $this->mockValidationServiceForCreate($merchant, $channel, $userChannel);

        // Storage mock for qrCodeS3Path called via buildResult -> paying()
        Storage::shouldReceive('disk')
            ->with('user-channel-accounts-qr-code')
            ->andReturnSelf();
        Storage::shouldReceive('temporaryUrl')
            ->andReturn('https://example.com/qr.jpg');

        $result = $this->service->create($context);

        $this->assertInstanceOf(CreateTransactionResult::class, $result);
        $this->assertEquals($transaction->id, $result->transaction->id);
        // paying() returns true → buildResult() → matched status
        $this->assertEquals('matched', $result->status);
    }

    public function test_create_returns_early_when_transaction_is_success(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $channel = $this->createChannel();
        $orderNumber = 'ORD_SUC_' . mt_rand(100000, 999999);

        $transaction = $this->createTransaction([
            'to_id' => $merchant->id,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'order_number' => $orderNumber,
            'channel_code' => $channel->code,
        ]);

        $userChannel = Mockery::mock(UserChannel::class);

        $context = $this->makeContext([
            'channelCode' => $channel->code,
            'username' => $merchant->username,
            'orderNumber' => $orderNumber,
        ]);

        $this->mockValidationServiceForCreate($merchant, $channel, $userChannel);

        $result = $this->service->create($context);

        $this->assertInstanceOf(CreateTransactionResult::class, $result);
        $this->assertEquals('success', $result->status);
        $this->assertEquals($transaction->id, $result->transaction->id);
    }

    public function test_create_returns_matching_timed_out_when_expired(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $channel = $this->createChannel([
            'order_timeout_enable' => true,
            'order_timeout' => 60,
        ]);
        $orderNumber = 'ORD_MTO_' . mt_rand(100000, 999999);

        // Carbon 3 diffInSeconds is signed: now()->diffInSeconds($future) > 0.
        // shouldMatchingTimedOut() checks: now()->diffInSeconds($this->created_at) >= timeout
        // So we need created_at to be in the future relative to "now" by at least timeout seconds.
        $frozenNow = now();
        Carbon::setTestNow($frozenNow);

        $transaction = $this->createTransaction([
            'to_id' => $merchant->id,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'order_number' => $orderNumber,
            'channel_code' => $channel->code,
            'created_at' => $frozenNow->copy()->addSeconds(120),
        ]);

        $userChannel = Mockery::mock(UserChannel::class);

        $context = $this->makeContext([
            'channelCode' => $channel->code,
            'username' => $merchant->username,
            'orderNumber' => $orderNumber,
        ]);

        $this->mockValidationServiceForCreate($merchant, $channel, $userChannel);

        $result = $this->service->create($context);

        Carbon::setTestNow(); // reset

        $this->assertInstanceOf(CreateTransactionResult::class, $result);
        $this->assertEquals('matching_timed_out', $result->status);

        // Verify transaction was updated in DB
        $transaction->refresh();
        $this->assertEquals(Transaction::STATUS_MATCHING_TIMED_OUT, $transaction->status);
    }

    public function test_create_returns_matching_timed_out_for_status(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $channel = $this->createChannel();
        $orderNumber = 'ORD_MTOS_' . mt_rand(100000, 999999);

        $transaction = $this->createTransaction([
            'to_id' => $merchant->id,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING_TIMED_OUT,
            'order_number' => $orderNumber,
            'channel_code' => $channel->code,
        ]);

        $userChannel = Mockery::mock(UserChannel::class);

        $context = $this->makeContext([
            'channelCode' => $channel->code,
            'username' => $merchant->username,
            'orderNumber' => $orderNumber,
        ]);

        $this->mockValidationServiceForCreate($merchant, $channel, $userChannel);

        $result = $this->service->create($context);

        $this->assertInstanceOf(CreateTransactionResult::class, $result);
        $this->assertEquals('matching_timed_out', $result->status);
        $this->assertEquals($transaction->id, $result->transaction->id);
    }

    public function test_create_returns_paying_timed_out(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $channel = $this->createChannel();
        $orderNumber = 'ORD_PTO_' . mt_rand(100000, 999999);

        $transaction = $this->createTransaction([
            'to_id' => $merchant->id,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING_TIMED_OUT,
            'order_number' => $orderNumber,
            'channel_code' => $channel->code,
        ]);

        $userChannel = Mockery::mock(UserChannel::class);

        $context = $this->makeContext([
            'channelCode' => $channel->code,
            'username' => $merchant->username,
            'orderNumber' => $orderNumber,
        ]);

        $this->mockValidationServiceForCreate($merchant, $channel, $userChannel);

        $result = $this->service->create($context);

        $this->assertInstanceOf(CreateTransactionResult::class, $result);
        $this->assertEquals('paying_timed_out', $result->status);
        $this->assertEquals($transaction->id, $result->transaction->id);
    }

    public function test_create_third_party_duplicate_order_throws(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $channel = $this->createChannel();
        $orderNumber = 'ORD_DUP_' . mt_rand(100000, 999999);

        $this->createTransaction([
            'to_id' => $merchant->id,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'order_number' => $orderNumber,
            'channel_code' => $channel->code,
        ]);

        $userChannel = Mockery::mock(UserChannel::class);

        $context = $this->makeContext([
            'channelCode' => $channel->code,
            'username' => $merchant->username,
            'orderNumber' => $orderNumber,
            'isThirdParty' => true,
        ]);

        $this->mockValidationServiceForCreate($merchant, $channel, $userChannel);

        $this->expectException(TransactionValidationException::class);

        $this->service->create($context);
    }

    // ===============================================================
    //  handleCallback()
    // ===============================================================

    public function test_handleCallback_finds_by_order_number(): void
    {
        $orderNumber = 'test_order_' . mt_rand(100000, 999999);

        $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'order_number' => $orderNumber,
            'system_order_number' => 'SYS' . mt_rand(100000, 999999),
            'thirdchannel_id' => null,
        ]);

        $request = Request::create('/callback/' . $orderNumber, 'POST');

        $result = $this->service->handleCallback($orderNumber, $request);

        $this->assertInstanceOf(CallbackResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals('success', $result->responseBody);
    }

    public function test_handleCallback_finds_by_system_order_number(): void
    {
        $orderNumber = 'REAL_ORD_' . mt_rand(100000, 999999);
        $systemOrderNumber = 'test_sys_' . mt_rand(100000, 999999);

        $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'order_number' => $orderNumber,
            'system_order_number' => $systemOrderNumber,
            'thirdchannel_id' => null,
        ]);

        $request = Request::create('/callback/' . $systemOrderNumber, 'POST');

        // The method first tries to find by order_number (won't match system_order_number),
        // then falls back to system_order_number lookup.
        $result = $this->service->handleCallback($systemOrderNumber, $request);

        // The transaction has "test" in system_order_number and no thirdchannel_id
        $this->assertInstanceOf(CallbackResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals('success', $result->responseBody);
    }

    public function test_handleCallback_returns_success_for_test_order(): void
    {
        $orderNumber = 'test_callback_' . mt_rand(100000, 999999);

        $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_THIRD_PAYING,
            'order_number' => $orderNumber,
            'system_order_number' => 'SYS' . mt_rand(100000, 999999),
            'thirdchannel_id' => null,
        ]);

        $request = Request::create('/callback/' . $orderNumber, 'POST');

        $result = $this->service->handleCallback($orderNumber, $request);

        $this->assertInstanceOf(CallbackResult::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals('success', $result->responseBody);
    }

    public function test_handleCallback_throws_when_transaction_not_found(): void
    {
        $orderNumber = 'NONEXISTENT_' . mt_rand(100000, 999999);
        $request = Request::create('/callback/' . $orderNumber, 'POST');

        $this->expectException(ModelNotFoundException::class);

        $this->service->handleCallback($orderNumber, $request);
    }

    public function test_handleCallback_requires_thirdchannel_for_non_test_order(): void
    {
        $orderNumber = 'REAL_ORD_' . mt_rand(100000, 999999);

        $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_THIRD_PAYING,
            'order_number' => $orderNumber,
            'system_order_number' => 'SYS' . mt_rand(100000, 999999),
            'thirdchannel_id' => 999999, // non-existent thirdchannel
        ]);

        $request = Request::create('/callback/' . $orderNumber, 'POST');

        // Non-test order with non-existent thirdchannel_id → ModelNotFoundException
        // from ThirdChannel::where('id', ...)->firstOrFail()
        $this->expectException(ModelNotFoundException::class);

        $this->service->handleCallback($orderNumber, $request);
    }

    // ===============================================================
    //  validateAndGenerateUrl()
    // ===============================================================

    public function test_validateAndGenerateUrl_throws_when_merchant_not_found(): void
    {
        $context = new DemoContext(
            channelCode: 'TEST_CH',
            username: 'NONEXISTENT_USER',
            secretKey: 'wrong_secret',
            amount: '100',
            orderNumber: 'ORD123',
            notifyUrl: 'https://example.com/notify',
        );

        $this->expectException(TransactionValidationException::class);

        $this->service->validateAndGenerateUrl($context);
    }

    public function test_validateAndGenerateUrl_throws_when_channel_not_found(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $context = new DemoContext(
            channelCode: 'NONEXISTENT_CHANNEL',
            username: $merchant->username,
            secretKey: $merchant->secret_key,
            amount: '100',
            orderNumber: 'ORD123',
            notifyUrl: 'https://example.com/notify',
        );

        $this->expectException(TransactionValidationException::class);

        $this->service->validateAndGenerateUrl($context);
    }

    public function test_validateAndGenerateUrl_throws_when_user_channel_not_found(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $channel = $this->createChannel();

        $context = new DemoContext(
            channelCode: $channel->code,
            username: $merchant->username,
            secretKey: $merchant->secret_key,
            amount: '100',
            orderNumber: 'ORD123',
            notifyUrl: 'https://example.com/notify',
        );

        $this->validationService
            ->shouldReceive('findSuitableUserChannel')
            ->once()
            ->with(
                Mockery::type(User::class),
                Mockery::type(Channel::class),
                '100'
            )
            ->andReturn([null, null]);

        $this->expectException(TransactionValidationException::class);

        $this->service->validateAndGenerateUrl($context);
    }

    public function test_validateAndGenerateUrl_returns_url_on_success(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $channel = $this->createChannel();

        $context = new DemoContext(
            channelCode: $channel->code,
            username: $merchant->username,
            secretKey: $merchant->secret_key,
            amount: '100',
            orderNumber: 'ORD123',
            notifyUrl: 'https://example.com/notify',
            returnUrl: 'https://example.com/return',
        );

        $userChannel = Mockery::mock(UserChannel::class);
        $userChannel->shouldReceive('isDisabled')->once()->andReturn(false);

        $this->validationService
            ->shouldReceive('findSuitableUserChannel')
            ->once()
            ->with(
                Mockery::type(User::class),
                Mockery::type(Channel::class),
                '100'
            )
            ->andReturn([$userChannel, null]);

        $this->validationService
            ->shouldReceive('withSign')
            ->once()
            ->andReturnUsing(function ($postData) {
                return $postData->merge(['sign' => 'generated_sign']);
            });

        $result = $this->service->validateAndGenerateUrl($context);

        $this->assertInstanceOf(DemoResult::class, $result);
        $this->assertNotEmpty($result->url);
        $this->assertStringContainsString('channel_code=' . $channel->code, $result->url);
        $this->assertStringContainsString('username=' . $merchant->username, $result->url);
    }
}
