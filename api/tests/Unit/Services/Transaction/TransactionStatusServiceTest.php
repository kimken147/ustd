<?php

namespace Tests\Unit\Services\Transaction;

use App\Exceptions\InvalidStatusException;
use App\Exceptions\RaceConditionException;
use App\Exceptions\TransactionRefundedException;
use App\Jobs\NotifyTransaction;
use App\Jobs\ProcessUsdtWithdraw;
use App\Models\Channel;
use App\Models\FeatureToggle;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\TransactionNote;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionFeeService;
use App\Services\Transaction\TransactionSeparationService;
use App\Services\Transaction\TransactionSettlementService;
use App\Services\Transaction\TransactionStateValidator;
use App\Services\Transaction\TransactionStatusService;
use App\Utils\BCMathUtil;
use App\Utils\UserChannelAccountUtil;
use App\Utils\WalletUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TransactionStatusServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TransactionStatusService $service;
    private $walletUtil;
    private BCMathUtil $bcMath;
    private $featureToggleRepository;
    private $transactionFeeService;
    private $userChannelAccountUtil;
    private $stateValidator;
    private $settlementService;
    private $separationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->walletUtil = Mockery::mock(WalletUtil::class);
        $this->bcMath = new BCMathUtil();
        $this->featureToggleRepository = Mockery::mock(FeatureToggleRepository::class);
        $this->transactionFeeService = Mockery::mock(TransactionFeeService::class);
        $this->userChannelAccountUtil = Mockery::mock(UserChannelAccountUtil::class);
        $this->stateValidator = Mockery::mock(TransactionStateValidator::class);
        $this->settlementService = Mockery::mock(TransactionSettlementService::class);
        $this->separationService = Mockery::mock(TransactionSeparationService::class);

        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::CANCEL_PAUFEN_MECHANISM)
            ->andReturn(false)
            ->byDefault();

        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::NOTIFY_NON_SUCCESS_USER_CHANNEL_ACCOUNT)
            ->andReturn(false)
            ->byDefault();

        $this->service = new TransactionStatusService(
            $this->walletUtil,
            $this->bcMath,
            $this->featureToggleRepository,
            $this->transactionFeeService,
            $this->userChannelAccountUtil,
            $this->stateValidator,
            $this->settlementService,
            $this->separationService
        );
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function rebuildServiceWithCancelPaufen(bool $enabled): void
    {
        $repo = Mockery::mock(FeatureToggleRepository::class);
        $repo->shouldReceive('enabled')
            ->with(FeatureToggle::CANCEL_PAUFEN_MECHANISM)
            ->andReturn($enabled);
        $repo->shouldReceive('enabled')
            ->withAnyArgs()
            ->andReturn(false)
            ->byDefault();

        $this->featureToggleRepository = $repo;

        $this->service = new TransactionStatusService(
            $this->walletUtil,
            $this->bcMath,
            $this->featureToggleRepository,
            $this->transactionFeeService,
            $this->userChannelAccountUtil,
            $this->stateValidator,
            $this->settlementService,
            $this->separationService
        );
    }

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

    private function createProviderWithWallet(array $overrides = []): User
    {
        $userId = $this->createUser(array_merge(['role' => User::ROLE_PROVIDER], $overrides));
        $this->createWallet($userId);

        return User::find($userId);
    }

    private function createAdminUser(): User
    {
        $userId = $this->createUser(['role' => User::ROLE_ADMIN]);
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

    private function createTransactionFee(int $transactionId, int $userId, array $overrides = []): void
    {
        $defaults = [
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
            'thirdchannel_id' => null,
            'profit' => '5.000000',
            'actual_profit' => '0',
            'fee' => '3.000000',
            'actual_fee' => '0',
        ];

        DB::table('transaction_fees')->insert(array_merge($defaults, $overrides));
    }

    private function makeThirdChannel(int $id = 1): ThirdChannel
    {
        $tc = new ThirdChannel();
        $tc->id = $id;
        $tc->exists = true;

        return $tc;
    }

    // ===============================================================
    //  markAsReceived()
    // ===============================================================

    public function test_markAsReceived_updates_status_and_operator(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $operator = $this->createAdminUser();

        $transaction = $this->createTransaction([
            'from_id' => $merchant->id,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
        ]);

        $result = $this->service->markAsReceived($transaction, $operator);

        $this->assertEquals(Transaction::STATUS_RECEIVED, $result->status);
        $this->assertEquals($operator->id, $result->operator_id);
        $this->assertNotNull($result->operated_at);
    }

    public function test_markAsReceived_throws_RaceConditionException_when_status_not_allowed(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_MATCHING,
        ]);

        $this->expectException(RaceConditionException::class);
        $this->service->markAsReceived($transaction);
    }

    public function test_markAsReceived_works_without_operator(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
        ]);

        $result = $this->service->markAsReceived($transaction);

        $this->assertEquals(Transaction::STATUS_RECEIVED, $result->status);
        $this->assertNull($result->operator_id);
    }

    // ===============================================================
    //  rollbackAsPaying()
    // ===============================================================

    public function test_rollbackAsPaying_throws_when_type_not_paufen_withdraw(): void
    {
        $operator = $this->createAdminUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
        ]);

        // throw_if with int status code causes "Can only throw objects" Error
        $this->expectException(\Error::class);
        $this->service->rollbackAsPaying($transaction, $operator);
    }

    public function test_rollbackAsPaying_throws_RaceConditionException_when_conditions_not_met(): void
    {
        $operator = $this->createAdminUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'to_wallet_settled' => false,
        ]);

        $this->expectException(RaceConditionException::class);
        $this->service->rollbackAsPaying($transaction, $operator);
    }

    public function test_rollbackAsPaying_happy_path(): void
    {
        $operator = $this->createAdminUser();
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
            'to_wallet_settled' => false,
            'actual_amount' => '100.000000',
            'confirmed_at' => now(),
        ]);

        $this->settlementService->shouldReceive('rollbackProfit')
            ->once()
            ->with(Mockery::type(Transaction::class));

        $result = $this->service->rollbackAsPaying($transaction, $operator);

        $result->refresh();
        $this->assertEquals(Transaction::STATUS_PAYING, $result->status);
        $this->assertEquals(0, (float) $result->actual_amount);
        $this->assertEquals($operator->id, $result->operator_id);
        $this->assertNull($result->confirmed_at);
    }

    // ===============================================================
    //  markAsNormalWithdraw()
    // ===============================================================

    public function test_markAsNormalWithdraw_aborts_when_type_not_allowed(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'locked_at' => now(),
            'locked_by_id' => $admin->id,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->markAsNormalWithdraw($transaction, null, false);
    }

    public function test_markAsNormalWithdraw_aborts_when_status_not_allowed(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
            'locked_at' => now(),
            'locked_by_id' => $admin->id,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->markAsNormalWithdraw($transaction, null, false);
    }

    public function test_markAsNormalWithdraw_happy_path(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $provider = $this->createProviderWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $admin->id,
            'to_id' => $provider->id,
            'locked_at' => now(),
            'locked_by_id' => $admin->id,
        ]);

        // Create provider fee to verify deletion
        $this->createTransactionFee($transaction->id, $provider->id);

        $result = $this->service->markAsNormalWithdraw($transaction, null, false);

        $this->assertEquals(Transaction::TYPE_NORMAL_WITHDRAW, $result->type);
        $this->assertEquals(Transaction::STATUS_PAYING, $result->status);
        $this->assertEquals(0, $result->to_id);
        $this->assertNull($result->to_wallet_id);
        $this->assertNull($result->locked_at);
        $this->assertNull($result->locked_by_id);

        // Provider fees should be deleted
        $providerFees = TransactionFee::where('transaction_id', $transaction->id)
            ->where('user_id', $provider->id)
            ->count();
        $this->assertEquals(0, $providerFees);
    }

    public function test_markAsNormalWithdraw_preserves_lock_when_keepLock_true(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'locked_at' => now(),
            'locked_by_id' => $admin->id,
        ]);

        $result = $this->service->markAsNormalWithdraw($transaction, null, false, true);

        $this->assertNotNull($result->locked_at);
        $this->assertEquals($admin->id, $result->locked_by_id);
    }

    public function test_markAsNormalWithdraw_updates_note_when_provided(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'locked_at' => now(),
            'locked_by_id' => $admin->id,
            'note' => 'old note',
        ]);

        $result = $this->service->markAsNormalWithdraw($transaction, 'new note', false);

        $this->assertEquals('new note', $result->note);
    }

    public function test_markAsNormalWithdraw_dispatches_ProcessUsdtWithdraw_when_conditions_met(): void
    {
        Bus::fake([ProcessUsdtWithdraw::class]);

        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'locked_at' => now(),
            'locked_by_id' => $admin->id,
            'channel_code' => Channel::CODE_USDT,
            'thirdchannel_id' => null,
            'from_channel_account_id' => 999,
        ]);

        // keepLock=false, channel=USDT, no thirdchannel, has from_channel_account_id
        $this->service->markAsNormalWithdraw($transaction, null, false, false);

        Bus::assertDispatched(ProcessUsdtWithdraw::class);
    }

    // ===============================================================
    //  markAsPaufenWithdraw()
    // ===============================================================

    public function test_markAsPaufenWithdraw_aborts_when_provider_role_not_provider(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->markAsPaufenWithdraw($transaction, $merchant, false);
    }

    public function test_markAsPaufenWithdraw_aborts_when_type_not_allowed(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);
        $provider = $this->createProviderWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->markAsPaufenWithdraw($transaction, $provider, false);
    }

    public function test_markAsPaufenWithdraw_aborts_when_status_not_allowed(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);
        $provider = $this->createProviderWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->markAsPaufenWithdraw($transaction, $provider, false);
    }

    public function test_markAsPaufenWithdraw_happy_path_with_provider(): void
    {
        if (!Schema::hasColumn('transactions', 'to_channel_account_id')) {
            $this->markTestSkipped('to_channel_account_id column not present in test DB');
        }

        $admin = $this->createAdminUser();
        $this->actingAs($admin);
        $provider = $this->createProviderWithWallet();
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'sub_type' => Transaction::SUB_TYPE_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);

        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->userChannelAccountUtil->shouldReceive('updateTotalRollback')
            ->byDefault();

        $this->transactionFeeService->shouldReceive('createWithdrawFees')
            ->once()
            ->with(Mockery::type(Transaction::class), Mockery::type(User::class), false);

        $this->transactionFeeService->shouldReceive('createDepositFees')
            ->once()
            ->with(Mockery::type(Transaction::class), Mockery::type(User::class), false);

        $result = $this->service->markAsPaufenWithdraw($transaction, $provider, false);

        $this->assertEquals(Transaction::TYPE_PAUFEN_WITHDRAW, $result->type);
        $this->assertEquals(Transaction::STATUS_PAYING, $result->status);
        $this->assertEquals($provider->id, $result->to_id);
        $this->assertNotNull($result->matched_at);
    }

    public function test_markAsPaufenWithdraw_happy_path_without_provider(): void
    {
        if (!Schema::hasColumn('transactions', 'to_channel_account_id')) {
            $this->markTestSkipped('to_channel_account_id column not present in test DB');
        }

        $admin = $this->createAdminUser();
        $this->actingAs($admin);
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'sub_type' => Transaction::SUB_TYPE_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);

        $this->userChannelAccountUtil->shouldReceive('updateTotalRollback')
            ->byDefault();

        $this->transactionFeeService->shouldReceive('createWithdrawFees')
            ->once();

        $this->transactionFeeService->shouldReceive('createDepositFees')
            ->never();

        $result = $this->service->markAsPaufenWithdraw($transaction, null, false);

        $this->assertEquals(Transaction::TYPE_PAUFEN_WITHDRAW, $result->type);
        $this->assertEquals(Transaction::STATUS_MATCHING, $result->status);
        $this->assertNull($result->to_id);
        $this->assertNull($result->matched_at);
    }

    // ===============================================================
    //  markAsThirdChannelWithdraw()
    // ===============================================================

    public function test_markAsThirdChannelWithdraw_aborts_when_type_or_order_number_invalid(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $thirdChannel = $this->makeThirdChannel();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'order_number' => 'ORD123',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->markAsThirdChannelWithdraw($transaction, $thirdChannel, false);
    }

    public function test_markAsThirdChannelWithdraw_aborts_when_status_not_allowed(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $thirdChannel = $this->makeThirdChannel();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_SUCCESS,
            'order_number' => 'ORD123',
        ]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->markAsThirdChannelWithdraw($transaction, $thirdChannel, false);
    }

    public function test_markAsThirdChannelWithdraw_happy_path(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);
        $merchant = $this->createMerchantWithWallet();

        $thirdChannel = $this->makeThirdChannel();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'sub_type' => Transaction::SUB_TYPE_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'order_number' => 'ORD123',
            'locked_at' => now(),
            'locked_by_id' => $admin->id,
        ]);

        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->transactionFeeService->shouldReceive('createWithdrawFees')
            ->once();

        $result = $this->service->markAsThirdChannelWithdraw($transaction, $thirdChannel, false);

        $this->assertEquals(Transaction::STATUS_THIRD_PAYING, $result->status);
        $this->assertEquals($thirdChannel->id, $result->thirdchannel_id);
        $this->assertNotNull($result->matched_at);
    }

    public function test_markAsThirdChannelWithdraw_aborts_when_no_order_number(): void
    {
        $admin = $this->createAdminUser();
        $this->actingAs($admin);

        $thirdChannel = $this->makeThirdChannel();

        // Force order_number to null
        $id = DB::table('transactions')->insertGetId([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => Transaction::NOTIFY_STATUS_NONE,
            'from_channel_account' => json_encode([]),
            'to_channel_account' => json_encode([]),
            'amount' => '100.000000',
            'floating_amount' => '100.000000',
            'actual_amount' => '0',
        ]);
        // Override system_order_number and order_number set by boot
        DB::table('transactions')->where('id', $id)->update(['order_number' => null]);
        $transaction = Transaction::find($id);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->service->markAsThirdChannelWithdraw($transaction, $thirdChannel, false);
    }

    // ===============================================================
    //  markAsRefunded()
    // ===============================================================

    public function test_markAsRefunded_throws_when_already_refunded(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
            'refunded_at' => now(),
        ]);

        $this->expectException(TransactionRefundedException::class);
        $this->service->markAsRefunded($transaction, null, null, false);
    }

    public function test_markAsRefunded_allows_pending_review_even_if_refunded_at_set(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_PENDING_REVIEW,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'refunded_at' => now(),
        ]);

        // STATUS_PENDING_REVIEW is in the exception list, so no throw even if refunded_at is set
        $result = $this->service->markAsRefunded($transaction, null, null, false);
        $this->assertNotNull($result);
    }

    public function test_markAsRefunded_refunds_paufen_transaction(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $operator = $this->createAdminUser();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);

        // refundYet() = true because refunded_at is null, refunded_by_id is null, from_id is set
        $this->walletUtil->shouldReceive('withdrawRollback')
            ->once()
            ->with(
                Mockery::type(\App\Models\Wallet::class),
                $transaction->floating_amount,
                $transaction->system_order_number,
                'transaction'
            );

        $result = $this->service->markAsRefunded($transaction, $operator, null, false);

        $result->refresh();
        $this->assertEquals($operator->id, $result->refunded_by_id);
        $this->assertNotNull($result->refunded_at);
    }

    public function test_markAsRefunded_skips_refund_for_non_paufen_type(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);

        $this->walletUtil->shouldReceive('withdrawRollback')->never();

        $result = $this->service->markAsRefunded($transaction, null, null, false);
        $this->assertNotNull($result);
    }

    // ===============================================================
    //  markAsSuccess()
    // ===============================================================

    public function test_markAsSuccess_returns_early_when_already_successful(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_SUCCESS,
        ]);

        // No mock expectations — nothing should be called
        $result = $this->service->markAsSuccess($transaction);
        $this->assertEquals(Transaction::STATUS_SUCCESS, $result->status);
    }

    public function test_markAsSuccess_throws_when_transition_not_allowed(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_FAILED,
        ]);

        $this->expectException(InvalidStatusException::class);
        $this->service->markAsSuccess($transaction);
    }

    public function test_markAsSuccess_throws_when_paying_timed_out_without_flag(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING_TIMED_OUT,
        ]);

        // fromPayingTimedOut=false but status is PAYING_TIMED_OUT → not in allowed statuses
        $this->expectException(InvalidStatusException::class);
        $this->service->markAsSuccess($transaction, null, false, false);
    }

    public function test_markAsSuccess_allows_paying_timed_out_with_flag(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $provider = $this->createProviderWithWallet();
        $operator = $this->createAdminUser();

        // refundYet() returns false when refunded_at is set → !refundYet()=true → needsPaufenRedepositionOnSuccess returns true
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING_TIMED_OUT,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'to_id' => $provider->id,
            'to_wallet_id' => $provider->wallet->id,
            'refunded_at' => now(),
        ]);

        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateLockBeforeUpdate')->once();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        // needsPaufenRedepositionOnSuccess: type=PAUFEN_TRANSACTION, fromPayingTimedOut=true, !refundYet()=true, cancelPaufen=false → true
        $this->walletUtil->shouldReceive('withdraw')
            ->once();

        $result = $this->service->markAsSuccess($transaction, $operator, false, true, true);

        $result->refresh();
        $this->assertContains($result->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    }

    public function test_markAsSuccess_calls_validatePaufenLock(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $operator = $this->createAdminUser();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')
            ->once()
            ->with(Mockery::type(Transaction::class), Mockery::type(User::class));

        $this->stateValidator->shouldReceive('validateLockBeforeUpdate')->once();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        $this->service->markAsSuccess($transaction, $operator, false, false, true);
    }

    public function test_markAsSuccess_calls_validateLockBeforeUpdate_when_shouldLock_true(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $operator = $this->createAdminUser();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateLockBeforeUpdate')
            ->once()
            ->with(Mockery::type(Transaction::class), Mockery::type(User::class));
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        $this->service->markAsSuccess($transaction, $operator, false, false, true);
    }

    public function test_markAsSuccess_skips_lock_validation_when_shouldLock_false(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateLockBeforeUpdate')->never();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        $this->service->markAsSuccess($transaction, null, false, false, false);
    }

    public function test_markAsSuccess_sets_auto_success_status(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        $result = $this->service->markAsSuccess($transaction, null, true, false, false);

        $this->assertEquals(Transaction::STATUS_SUCCESS, $result->status);
    }

    public function test_markAsSuccess_sets_manual_success_status(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $operator = $this->createAdminUser();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateLockBeforeUpdate')->once();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        $result = $this->service->markAsSuccess($transaction, $operator, false, false, true);

        $this->assertEquals(Transaction::STATUS_MANUAL_SUCCESS, $result->status);
    }

    public function test_markAsSuccess_actualizes_fees(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);

        $this->createTransactionFee($transaction->id, $merchant->id, [
            'fee' => '3.000000',
            'actual_fee' => '0',
            'profit' => '5.000000',
            'actual_profit' => '0',
        ]);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        $this->service->markAsSuccess($transaction, null, true, false, false);

        $fee = TransactionFee::where('transaction_id', $transaction->id)
            ->where('user_id', $merchant->id)
            ->first();
        $this->assertEquals(3, (float) $fee->actual_fee);
        $this->assertEquals(5, (float) $fee->actual_profit);
    }

    public function test_markAsSuccess_calls_settlement_services(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')
            ->once()
            ->with(Mockery::type(Transaction::class));

        $this->settlementService->shouldReceive('settleToWallet')
            ->once()
            ->with(Mockery::type(Transaction::class), null);

        $this->service->markAsSuccess($transaction, null, true, false, false);
    }

    public function test_markAsSuccess_dispatches_NotifyTransaction_when_notify_url_present(): void
    {
        Bus::fake([NotifyTransaction::class]);

        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'notify_url' => 'https://example.com/notify',
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        $this->service->markAsSuccess($transaction, null, true, false, false);

        Bus::assertDispatched(NotifyTransaction::class);
    }

    public function test_markAsSuccess_skips_notify_when_no_notify_url(): void
    {
        Bus::fake([NotifyTransaction::class]);

        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'notify_url' => null,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validatePaufenLock')->once();
        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeSuccess')->once();

        $this->settlementService->shouldReceive('settleProfit')->once();
        $this->settlementService->shouldReceive('settleToWallet')->once();

        $this->service->markAsSuccess($transaction, null, true, false, false);

        Bus::assertNotDispatched(NotifyTransaction::class);
    }

    // ===============================================================
    //  markAsFailed()
    // ===============================================================

    public function test_markAsFailed_throws_when_already_failed(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_FAILED,
        ]);

        $this->expectException(InvalidStatusException::class);
        $this->service->markAsFailed($transaction);
    }

    public function test_markAsFailed_throws_when_status_transition_not_allowed(): void
    {
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING_TIMED_OUT,
        ]);

        $this->expectException(InvalidStatusException::class);
        $this->service->markAsFailed($transaction, null, null, false);
    }

    public function test_markAsFailed_calls_validateNotSeparatedParent(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')
            ->once()
            ->with(Mockery::type(Transaction::class));
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        $this->walletUtil->shouldReceive('withdrawRollback')->once();
        $this->settlementService->shouldReceive('rollbackPaufenSettlement')->once();

        $this->service->markAsFailed($transaction, null, null, false);
    }

    public function test_markAsFailed_calls_validateLockBeforeUpdate_when_shouldLock_true(): void
    {
        $merchant = $this->createMerchantWithWallet();
        $operator = $this->createAdminUser();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateLockBeforeUpdate')
            ->once()
            ->with(Mockery::type(Transaction::class), Mockery::type(User::class));
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        $this->walletUtil->shouldReceive('withdrawRollback')->once();
        $this->settlementService->shouldReceive('rollbackPaufenSettlement')->once();

        $this->service->markAsFailed($transaction, $operator, null, true);
    }

    public function test_markAsFailed_skips_lock_validation_when_shouldLock_false(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateLockBeforeUpdate')->never();
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        $this->walletUtil->shouldReceive('withdrawRollback')->once();
        $this->settlementService->shouldReceive('rollbackPaufenSettlement')->once();

        $this->service->markAsFailed($transaction, null, null, false);
    }

    public function test_markAsFailed_zeroes_actual_fees(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);

        $this->createTransactionFee($transaction->id, $merchant->id, [
            'fee' => '3.000000',
            'actual_fee' => '3.000000',
            'profit' => '5.000000',
            'actual_profit' => '5.000000',
        ]);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        $this->walletUtil->shouldReceive('withdrawRollback')->once();
        $this->settlementService->shouldReceive('rollbackPaufenSettlement')->once();

        $this->service->markAsFailed($transaction, null, null, false);

        $fee = TransactionFee::where('transaction_id', $transaction->id)
            ->where('user_id', $merchant->id)
            ->first();
        $this->assertEquals(0, (float) $fee->actual_fee);
        $this->assertEquals(0, (float) $fee->actual_profit);
    }

    public function test_markAsFailed_creates_note_when_provided(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        $this->walletUtil->shouldReceive('withdrawRollback')->once();
        $this->settlementService->shouldReceive('rollbackPaufenSettlement')->once();

        $this->service->markAsFailed($transaction, null, 'failure reason', false);

        $note = TransactionNote::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($note);
        $this->assertEquals('failure reason', $note->note);
    }

    public function test_markAsFailed_paufen_transaction_refund(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        // refundYet = true (no refunded_at, no refunded_by_id, has from_id), cancelPaufen = false
        $this->walletUtil->shouldReceive('withdrawRollback')
            ->once()
            ->with(
                Mockery::type(\App\Models\Wallet::class),
                $transaction->floating_amount,
                Mockery::type('string'),
                'transaction'
            );

        $this->settlementService->shouldReceive('rollbackPaufenSettlement')
            ->once();

        $result = $this->service->markAsFailed($transaction, null, null, false);

        $result->refresh();
        $this->assertNotNull($result->refunded_at);
    }

    public function test_markAsFailed_paufen_transaction_skips_refund_when_already_refunded(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'refunded_at' => now(),
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        // refundYet = false because refunded_at is set
        $this->walletUtil->shouldReceive('withdrawRollback')->never();

        $this->settlementService->shouldReceive('rollbackPaufenSettlement')
            ->once();

        $this->service->markAsFailed($transaction, null, null, false);
    }

    public function test_markAsFailed_withdraw_type_refund(): void
    {
        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'sub_type' => Transaction::SUB_TYPE_WITHDRAW,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'order_number' => 'ORD123',
            'system_order_number' => 'SYS123',
        ]);

        $this->createTransactionFee($transaction->id, $merchant->id, [
            'fee' => '3.000000',
            'actual_fee' => '0',
            'profit' => '0',
            'actual_profit' => '0',
        ]);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        // Withdraw refund: amount + fee rollback
        $this->walletUtil->shouldReceive('withdrawRollback')
            ->once()
            ->with(
                Mockery::type(\App\Models\Wallet::class),
                Mockery::type('string'), // bcMath->add(amount, fee)
                Mockery::type('string'),
                'withdraw',
                'balance'
            );

        $this->settlementService->shouldReceive('rollbackWithdrawSettlement')
            ->once();

        $this->userChannelAccountUtil->shouldReceive('updateTotalRollback')
            ->byDefault();

        $result = $this->service->markAsFailed($transaction, null, null, false);

        $result->refresh();
        $this->assertNotNull($result->refunded_at);
    }

    public function test_markAsFailed_dispatches_NotifyTransaction(): void
    {
        Bus::fake([NotifyTransaction::class]);

        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'notify_url' => 'https://example.com/notify',
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        $this->walletUtil->shouldReceive('withdrawRollback')->once();
        $this->settlementService->shouldReceive('rollbackPaufenSettlement')->once();

        $this->service->markAsFailed($transaction, null, null, false);

        Bus::assertDispatched(NotifyTransaction::class);
    }

    public function test_markAsFailed_sets_status_and_notify_status(): void
    {
        Bus::fake([NotifyTransaction::class]);

        $merchant = $this->createMerchantWithWallet();

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => $merchant->id,
            'from_wallet_id' => $merchant->wallet->id,
            'notify_url' => 'https://example.com/notify',
        ]);
        $this->createTransactionFee($transaction->id, $merchant->id);

        $this->stateValidator->shouldReceive('validateNotSeparatedParent')->once();
        $this->stateValidator->shouldReceive('validateChildCanBeFailed')->once();

        $this->walletUtil->shouldReceive('withdrawRollback')->once();
        $this->settlementService->shouldReceive('rollbackPaufenSettlement')->once();

        $result = $this->service->markAsFailed($transaction, null, null, false);

        $this->assertEquals(Transaction::STATUS_FAILED, $result->status);
        $this->assertEquals(Transaction::NOTIFY_STATUS_PENDING, $result->notify_status);
    }
}
