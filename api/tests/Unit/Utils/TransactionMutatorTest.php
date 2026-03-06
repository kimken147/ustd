<?php

namespace Tests\Unit\Utils;

use App\Exceptions\RaceConditionException;
use App\Models\ChannelAmount;
use App\Models\ChannelGroup;
use App\Models\DevicePayingTransaction;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Models\Wallet;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionFeeService;
use App\Utils\BCMathUtil;
use App\Utils\InsufficientAvailableBalance;
use App\Utils\TransactionMutator;
use App\Utils\UserChannelAccountUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class TransactionMutatorTest extends TestCase
{
    use DatabaseTransactions;

    private BCMathUtil $bcMath;
    private $userChannelAccountUtil;
    private $featureToggleRepo;
    private $transactionFeeService;
    private TransactionMutator $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bcMath = new BCMathUtil();
        $this->userChannelAccountUtil = Mockery::mock(UserChannelAccountUtil::class);
        $this->featureToggleRepo = Mockery::mock(FeatureToggleRepository::class);
        $this->transactionFeeService = Mockery::mock(TransactionFeeService::class);

        // Default feature toggle returns
        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false)->byDefault();
        $this->featureToggleRepo->shouldReceive('valueOf')->andReturn(0)->byDefault();

        $this->service = new TransactionMutator(
            $this->bcMath,
            $this->userChannelAccountUtil,
            $this->featureToggleRepo,
            $this->transactionFeeService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => 'test_user',
            'username' => 'test_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_MERCHANT,
            'status' => User::STATUS_ENABLE,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    private function createWallet(int $userId, array $overrides = []): Wallet
    {
        $id = DB::table('wallets')->insertGetId(array_merge([
            'user_id' => $userId,
            'balance' => '10000.00',
            'profit' => '500.00',
            'frozen_balance' => '0.00',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return Wallet::find($id);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $id = DB::table('transactions')->insertGetId(array_merge([
            'from_id' => 0,
            'to_id' => null,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_MATCHING,
            'notify_status' => 0,
            'from_channel_account' => json_encode(['bank_name' => 'ICBC']),
            'to_channel_account' => json_encode([]),
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'actual_amount' => '0.00',
            'order_number' => 'TEST' . uniqid(),
            'system_order_number' => 'SYS' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return Transaction::find($id);
    }

    private function createDevice(int $userId, array $overrides = []): int
    {
        return DB::table('devices')->insertGetId(array_merge([
            'user_id' => $userId,
            'name' => 'test_device_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function createChannelGroup(array $overrides = []): ChannelGroup
    {
        $id = DB::table('channel_groups')->insertGetId(array_merge([
            'channel_code' => 'BANK',
            'fixed_amount' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return ChannelGroup::find($id);
    }

    private function createChannelAmount(int $channelGroupId, array $overrides = []): ChannelAmount
    {
        $uniqueSuffix = mt_rand(1, 999999);
        $id = DB::table('channel_amounts')->insertGetId(array_merge([
            'channel_group_id' => $channelGroupId,
            'channel_code' => 'BANK',
            'min_amount' => $uniqueSuffix . '.00',
            'max_amount' => ($uniqueSuffix + 1) . '.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return ChannelAmount::find($id);
    }

    private function createUserChannelAccount(int $userId, int $walletId, array $overrides = []): UserChannelAccount
    {
        $data = array_merge([
            'user_id' => $userId,
            'wallet_id' => $walletId,
            'status' => UserChannelAccount::STATUS_ONLINE,
            'type' => UserChannelAccount::TYPE_DEPOSIT_WITHDRAW,
            'account' => 'ACC_' . uniqid(),
            'detail' => json_encode(['bank_name' => 'ICBC', 'bank_card_number' => '6222000000000001']),
            'balance' => '5000.00',
            'daily_status' => UserChannelAccount::DAILY_STATUS_DISABLE,
            'daily_limit' => '0.00',
            'daily_total' => '0.00',
            'monthly_status' => UserChannelAccount::MONTHLY_STATUS_DISABLE,
            'monthly_limit' => '0.00',
            'monthly_total' => '0.00',
            'fee_percent' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        // Only include balance_limit if column exists and not already set
        if (Schema::hasColumn('user_channel_accounts', 'balance_limit') && !array_key_exists('balance_limit', $overrides)) {
            $data['balance_limit'] = 0;
        }

        // Only include name if column exists and not already set
        if (Schema::hasColumn('user_channel_accounts', 'name') && !array_key_exists('name', $overrides)) {
            $data['name'] = 'test_account_' . uniqid();
        }

        $id = DB::table('user_channel_accounts')->insertGetId($data);

        return UserChannelAccount::find($id);
    }

    private function createAccountWithChannelGroup(int $userId, int $walletId, array $accountOverrides = []): UserChannelAccount
    {
        $deviceId = $this->createDevice($userId);
        $channelGroup = $this->createChannelGroup();
        $channelAmount = $this->createChannelAmount($channelGroup->id);

        return $this->createUserChannelAccount($userId, $walletId, array_merge([
            'channel_amount_id' => $channelAmount->id,
            'device_id' => $deviceId,
        ], $accountOverrides));
    }

    // ---------------------------------------------------------------
    //  paufenDepositTo() tests
    // ---------------------------------------------------------------

    public function test_paufenDepositTo_happy_path_updates_transaction_and_creates_fees(): void
    {
        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_MATCHING,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'to_id' => null,
            'locked_at' => null,
        ]);

        $this->transactionFeeService
            ->shouldReceive('createDepositFees')
            ->once()
            ->with(
                Mockery::on(fn ($t) => $t->id === $transaction->id),
                Mockery::on(fn ($u) => $u->id === $provider->id),
                false,
                null
            );

        $result = $this->service->paufenDepositTo($provider, $transaction);

        $this->assertSame($transaction->id, $result->id);

        $updated = Transaction::find($transaction->id);
        $this->assertEquals($provider->id, $updated->to_id);
        $this->assertEquals($wallet->id, $updated->to_wallet_id);
        $this->assertEquals($provider->account_mode, $updated->to_account_mode);
        $this->assertEquals(Transaction::STATUS_PAYING, $updated->status);
        $this->assertNotNull($updated->matched_at);
    }

    public function test_paufenDepositTo_throws_race_condition_when_already_paying(): void
    {
        $provider = $this->createUser();
        $this->createWallet($provider->id);
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_PAYING,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'to_id' => null,
            'locked_at' => null,
        ]);

        $this->expectException(RaceConditionException::class);

        $this->service->paufenDepositTo($provider, $transaction);
    }

    public function test_paufenDepositTo_throws_runtime_when_status_still_matching_but_update_fails(): void
    {
        $provider = $this->createUser();
        $this->createWallet($provider->id);

        // to_id is set so the WHERE to_id IS NULL condition fails,
        // but status is still STATUS_MATCHING
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_MATCHING,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'to_id' => 999,
            'locked_at' => null,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown');

        $this->service->paufenDepositTo($provider, $transaction);
    }

    public function test_paufenDepositTo_passes_parent_to_createDepositFees(): void
    {
        $provider = $this->createUser();
        $this->createWallet($provider->id);

        $parent = $this->createTransaction([
            'status' => Transaction::STATUS_PAYING,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
        ]);

        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_MATCHING,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'to_id' => null,
            'locked_at' => null,
            'parent_id' => $parent->id,
        ]);

        $parentId = $parent->id;

        $this->transactionFeeService
            ->shouldReceive('createDepositFees')
            ->once()
            ->with(
                Mockery::on(fn ($t) => $t->id === $transaction->id),
                Mockery::on(fn ($u) => $u->id === $provider->id),
                false,
                Mockery::on(fn ($p) => $p instanceof Transaction && $p->id === $parentId)
            );

        $result = $this->service->paufenDepositTo($provider, $transaction, $parent);

        $this->assertSame($transaction->id, $result->id);
    }

    // ---------------------------------------------------------------
    //  paufenDepositToAccount() tests
    // ---------------------------------------------------------------

    public function test_paufenDepositToAccount_happy_path(): void
    {
        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);

        $account = $this->createUserChannelAccount($provider->id, $wallet->id, [
            'balance' => '5000.00',
        ]);

        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_MATCHING,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'to_id' => null,
            'locked_at' => null,
            'from_channel_account' => json_encode(['bank_name' => 'ICBC']),
        ]);

        // paufenDepositToAccount calls app(FeatureToggleRepository::class)
        $containerRepo = Mockery::mock(FeatureToggleRepository::class);
        $containerRepo->shouldReceive('enabled')
            ->with(FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE, false)
            ->andReturn(false);
        $this->app->instance(FeatureToggleRepository::class, $containerRepo);

        $this->transactionFeeService
            ->shouldReceive('createDepositFees')
            ->once()
            ->with(
                Mockery::on(fn ($t) => $t->id === $transaction->id),
                Mockery::on(fn ($u) => $u->id === $provider->id),
                false,
                null
            );

        $this->userChannelAccountUtil
            ->shouldReceive('updateTotal')
            ->once()
            ->with(
                $account->id,
                Mockery::type('string'),
                true
            );

        $result = $this->service->paufenDepositToAccount($account, $transaction);

        $this->assertSame($transaction->id, $result->id);

        $updated = Transaction::find($transaction->id);
        $this->assertEquals($provider->id, $updated->to_id);
        $this->assertEquals($wallet->id, $updated->to_wallet_id);
        $this->assertEquals($account->id, $updated->to_channel_account_id);
        $this->assertEquals(Transaction::STATUS_PAYING, $updated->status);
        $this->assertNotNull($updated->matched_at);

        // Verify from_channel_account is preserved
        $this->assertIsArray($updated->from_channel_account);
    }

    public function test_paufenDepositToAccount_throws_insufficient_balance_when_enabled(): void
    {
        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);

        $account = $this->createUserChannelAccount($provider->id, $wallet->id, [
            'balance' => '50.00',
        ]);

        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_MATCHING,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'to_id' => null,
            'locked_at' => null,
            'floating_amount' => '100.00',
        ]);

        // app(FeatureToggleRepository) returns enabled for RECORD_USER_CHANNEL_ACCOUNT_BALANCE
        $containerRepo = Mockery::mock(FeatureToggleRepository::class);
        $containerRepo->shouldReceive('enabled')
            ->with(FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE, false)
            ->andReturn(true);
        $this->app->instance(FeatureToggleRepository::class, $containerRepo);

        $this->expectException(InsufficientAvailableBalance::class);

        $this->service->paufenDepositToAccount($account, $transaction);
    }

    public function test_paufenDepositToAccount_race_condition_when_status_not_matching(): void
    {
        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);

        $account = $this->createUserChannelAccount($provider->id, $wallet->id);

        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_PAYING,
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'to_id' => null,
            'locked_at' => null,
        ]);

        $containerRepo = Mockery::mock(FeatureToggleRepository::class);
        $containerRepo->shouldReceive('enabled')
            ->with(FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE, false)
            ->andReturn(false);
        $this->app->instance(FeatureToggleRepository::class, $containerRepo);

        $this->expectException(RaceConditionException::class);

        $this->service->paufenDepositToAccount($account, $transaction);
    }

    // ---------------------------------------------------------------
    //  paufenTransactionFrom() tests
    // ---------------------------------------------------------------

    public function test_paufenTransactionFrom_happy_path_updates_and_creates_fees(): void
    {
        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);

        $account = $this->createAccountWithChannelGroup($provider->id, $wallet->id);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'from_id' => 0,
        ]);

        // CANCEL_PAUFEN_MECHANISM = false => DevicePayingTransaction will be created
        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::CANCEL_PAUFEN_MECHANISM)
            ->andReturn(false);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)
            ->andReturn(false);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT)
            ->andReturn(false);

        $this->transactionFeeService
            ->shouldReceive('createPaufenTransactionFees')
            ->once()
            ->with(
                Mockery::on(fn ($t) => $t->id === $transaction->id),
                Mockery::on(fn ($cg) => $cg instanceof ChannelGroup)
            );

        $result = $this->service->paufenTransactionFrom($account, $transaction);

        $updated = Transaction::find($transaction->id);
        $this->assertEquals($provider->id, $updated->from_id);
        $this->assertEquals($wallet->id, $updated->from_wallet_id);
        $this->assertEquals($account->id, $updated->from_channel_account_id);
        $this->assertEquals(Transaction::STATUS_PAYING, $updated->status);
        $this->assertNotNull($updated->matched_at);

        // Verify DevicePayingTransaction created
        $devicePayingTx = DB::table('device_paying_transactions')
            ->where('transaction_id', $transaction->id)
            ->first();
        $this->assertNotNull($devicePayingTx);
        $this->assertEquals($account->id, $devicePayingTx->user_channel_account_id);
    }

    public function test_paufenTransactionFrom_skips_device_record_when_cancel_paufen(): void
    {
        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);

        $account = $this->createAccountWithChannelGroup($provider->id, $wallet->id);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'from_id' => 0,
        ]);

        // CANCEL_PAUFEN_MECHANISM = true => no DevicePayingTransaction
        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::CANCEL_PAUFEN_MECHANISM)
            ->andReturn(true);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)
            ->andReturn(false);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT)
            ->andReturn(false);

        $this->transactionFeeService
            ->shouldReceive('createPaufenTransactionFees')
            ->once();

        $this->service->paufenTransactionFrom($account, $transaction);

        $devicePayingTx = DB::table('device_paying_transactions')
            ->where('transaction_id', $transaction->id)
            ->first();
        $this->assertNull($devicePayingTx);
    }

    public function test_paufenTransactionFrom_throws_conflict_when_update_fails(): void
    {
        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);

        $account = $this->createAccountWithChannelGroup($provider->id, $wallet->id);

        // STATUS_PAYING instead of STATUS_MATCHING => update WHERE status=MATCHING fails
        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'from_id' => 0,
        ]);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)
            ->andReturn(false);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT)
            ->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Conflict');

        $this->service->paufenTransactionFrom($account, $transaction);
    }

    public function test_paufenTransactionFrom_throws_when_balance_limit_exceeded(): void
    {
        if (!Schema::hasColumn('user_channel_accounts', 'balance_limit')) {
            $this->markTestSkipped('balance_limit column not present in user_channel_accounts table');
        }

        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);

        $account = $this->createAccountWithChannelGroup($provider->id, $wallet->id, [
            'balance' => '90.00',
            'balance_limit' => '100.00',
        ]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'amount' => '20.00',
            'floating_amount' => '20.00',
        ]);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)
            ->andReturn(false);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT)
            ->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/餘度不足/');

        $this->service->paufenTransactionFrom($account, $transaction);
    }

    public function test_paufenTransactionFrom_throws_when_daily_limit_exceeded(): void
    {
        $provider = $this->createUser();
        $wallet = $this->createWallet($provider->id);

        $account = $this->createAccountWithChannelGroup($provider->id, $wallet->id, [
            'daily_status' => true,
            'daily_limit' => '100.00',
            'daily_total' => '90.00',
        ]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
            'amount' => '20.00',
            'floating_amount' => '20.00',
        ]);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)
            ->andReturn(true);

        $this->featureToggleRepo->shouldReceive('valueOf')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)
            ->andReturn(0);

        $this->featureToggleRepo->shouldReceive('enabled')
            ->with(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT)
            ->andReturn(false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/日收餘度不足/');

        $this->service->paufenTransactionFrom($account, $transaction);
    }
}
