<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\Bank;
use App\Models\ChannelGroup;
use App\Models\FeatureToggle;
use App\Models\MerchantThirdChannel;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Models\UserChannel;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionFeeCalculator;
use App\Services\Transaction\TransactionFeeService;
use App\Utils\BCMathUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class TransactionFeeServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TransactionFeeService $service;
    private BCMathUtil $bcMath;
    private $featureToggleRepository;
    private $calculator;
    private static bool $macroRegistered = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Register insertOnDuplicateKey as a macro (package not installed in test env)
        if (!self::$macroRegistered) {
            Builder::macro('insertOnDuplicateKey', function (array $values) {
                return $this->toBase()->insert($values);
            });
            self::$macroRegistered = true;
        }

        $this->bcMath = new BCMathUtil();
        $this->featureToggleRepository = Mockery::mock(FeatureToggleRepository::class);
        $this->calculator = Mockery::mock(TransactionFeeCalculator::class);

        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::CANCEL_PAUFEN_MECHANISM)
            ->andReturn(false)
            ->byDefault();

        $this->service = new TransactionFeeService(
            $this->bcMath,
            $this->featureToggleRepository,
            false,
            $this->calculator
        );
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function rebuildServiceWithCancelPaufen(bool $cancelPaufen): void
    {
        $this->service = new TransactionFeeService(
            $this->bcMath,
            $this->featureToggleRepository,
            $cancelPaufen,
            $this->calculator
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

    private function createUserWithNestedSet(array $overrides, int $lft, int $rgt): int
    {
        return $this->createUser(array_merge($overrides, [
            '_lft' => $lft,
            '_rgt' => $rgt,
        ]));
    }

    private function createWallet(int $userId, array $overrides = []): void
    {
        $defaults = [
            'user_id' => $userId,
            'status' => 1,
            'balance' => '1000.00',
            'profit' => '0.00',
            'frozen_balance' => '0.00',
            'withdraw_fee' => '1.00',
            'withdraw_profit_fee' => '0.00',
            'agency_withdraw_fee' => '0.50',
            'agency_withdraw_fee_dollar' => '1.00',
        ];

        DB::table('wallets')->insert(array_merge($defaults, $overrides));
    }

    private function createMerchantWithWallet(array $userOverrides = [], array $walletOverrides = []): User
    {
        $userId = $this->createUser(array_merge(['role' => User::ROLE_MERCHANT], $userOverrides));
        $this->createWallet($userId, $walletOverrides);

        return User::find($userId);
    }

    private function createProviderWithWallet(array $userOverrides = [], array $walletOverrides = []): User
    {
        $userId = $this->createUser(array_merge(['role' => User::ROLE_PROVIDER], $userOverrides));
        $this->createWallet($userId, $walletOverrides);

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
            'amount' => '100.00',
            'floating_amount' => '100.00',
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
            'profit' => '5.00',
            'actual_profit' => '0',
            'fee' => '3.00',
            'actual_fee' => '0',
        ];

        DB::table('transaction_fees')->insert(array_merge($defaults, $overrides));
    }

    private function createThirdChannel(array $overrides = []): ThirdChannel
    {
        $defaults = [
            'name' => 'TestThirdChannel',
            'class' => 'TestClass',
            'status' => ThirdChannel::STATUS_ENABLE,
            'type' => ThirdChannel::TYPE_DEPOSIT_WITHDRAW,
            'channel_code' => 'BANK',
            'custom_url' => '',
            'balance' => '0.00',
            'notify_balance' => '0.00',
            'auto_daifu_threshold' => '0.00',
        ];

        $id = DB::table('thirdchannel')->insertGetId(array_merge($defaults, $overrides));

        return ThirdChannel::find($id);
    }

    private function createMerchantThirdChannel(int $thirdChannelId, int $ownerId, array $overrides = []): MerchantThirdChannel
    {
        $defaults = [
            'thirdchannel_id' => $thirdChannelId,
            'owner_id' => $ownerId,
            'deposit_fee_percent' => '0.00',
            'daifu_fee_percent' => '0.00',
            'withdraw_fee' => '0.00',
            'deposit_min' => '0.00',
            'deposit_max' => '999999.00',
            'daifu_min' => '0.00',
            'daifu_max' => '999999.00',
        ];

        $id = DB::table('merchant_thirdchannel')->insertGetId(array_merge($defaults, $overrides));

        return MerchantThirdChannel::find($id);
    }

    private function createChannelGroup(array $overrides = []): ChannelGroup
    {
        $defaults = [
            'channel_code' => 'BANK',
        ];

        $id = DB::table('channel_groups')->insertGetId(array_merge($defaults, $overrides));

        return ChannelGroup::find($id);
    }

    private function createUserChannel(int $userId, int $channelGroupId, array $overrides = []): UserChannel
    {
        $defaults = [
            'user_id' => $userId,
            'channel_group_id' => $channelGroupId,
            'status' => UserChannel::STATUS_ENABLED,
            'fee_percent' => '2.00',
            'min_amount' => '10.00',
            'max_amount' => '50000.00',
        ];

        $id = DB::table('user_channels')->insertGetId(array_merge($defaults, $overrides));

        return UserChannel::find($id);
    }

    private function createBank(string $name, array $overrides = []): Bank
    {
        $defaults = [
            'name' => $name,
        ];

        $id = DB::table('banks')->insertGetId(array_merge($defaults, $overrides));

        return Bank::find($id);
    }

    /**
     * Build a two-level user tree for nested set queries.
     *
     * Returns [rootUser, childUser] where root is the ancestor of child.
     */
    private function buildTwoLevelUserTree(
        int $rootRole = User::ROLE_MERCHANT,
        int $childRole = User::ROLE_MERCHANT,
        array $rootWalletOverrides = [],
        array $childWalletOverrides = []
    ): array {
        $rootId = $this->createUserWithNestedSet([
            'role' => $rootRole,
            'name' => 'RootUser',
        ], 1001, 1004);
        $this->createWallet($rootId, $rootWalletOverrides);

        $childId = $this->createUserWithNestedSet([
            'role' => $childRole,
            'name' => 'ChildUser',
            'parent_id' => $rootId,
        ], 1002, 1003);
        $this->createWallet($childId, $childWalletOverrides);

        return [User::find($rootId), User::find($childId)];
    }

    // ===============================================================
    //  createDepositFees()
    // ===============================================================

    public function test_createDepositFees_creates_fees_for_ancestors_and_system(): void
    {
        [$rootUser, $childUser] = $this->buildTwoLevelUserTree();

        $transaction = $this->createTransaction([
            'from_id' => $childUser->id,
        ]);

        $this->service->createDepositFees($transaction, $childUser, true);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // 3 records: root user + child user + system (user_id=0)
        $this->assertCount(3, $fees);

        // Verify user fee records exist
        $userIds = $fees->pluck('user_id')->sort()->values()->toArray();
        $expectedIds = [0, $rootUser->id, $childUser->id];
        sort($expectedIds);
        $this->assertEquals($expectedIds, $userIds);

        // System record should have user_id = 0 and null account_mode
        $systemFee = $fees->where('user_id', 0)->first();
        $this->assertNotNull($systemFee);
        $this->assertNull($systemFee->account_mode);

        // User records should carry their account_mode
        $childFee = $fees->where('user_id', $childUser->id)->first();
        $this->assertEquals(User::ACCOUNT_MODE_GENERAL, $childFee->account_mode);
    }

    public function test_createDepositFees_without_system(): void
    {
        [$rootUser, $childUser] = $this->buildTwoLevelUserTree();

        $transaction = $this->createTransaction([
            'from_id' => $childUser->id,
        ]);

        $this->service->createDepositFees($transaction, $childUser, false);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // Only user fees (root + child), no system
        $this->assertCount(2, $fees);

        $userIds = $fees->pluck('user_id')->sort()->values()->toArray();
        $expectedIds = [$rootUser->id, $childUser->id];
        sort($expectedIds);
        $this->assertEquals($expectedIds, $userIds);

        // No system fee
        $this->assertNull($fees->where('user_id', 0)->first());
    }

    public function test_createDepositFees_single_user_no_ancestors(): void
    {
        $userId = $this->createUserWithNestedSet([
            'role' => User::ROLE_MERCHANT,
            'name' => 'SingleUser',
        ], 1011, 1012);
        $this->createWallet($userId);
        $user = User::find($userId);

        $transaction = $this->createTransaction([
            'from_id' => $user->id,
        ]);

        $this->service->createDepositFees($transaction, $user, true);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // 2 records: the user + system
        $this->assertCount(2, $fees);

        $userIds = $fees->pluck('user_id')->sort()->values()->toArray();
        $expectedIds = [0, $user->id];
        sort($expectedIds);
        $this->assertEquals($expectedIds, $userIds);
    }

    // ===============================================================
    //  createWithdrawFees()
    // ===============================================================

    public function test_createWithdrawFees_delegates_to_separate_when_parent_provided(): void
    {
        [$rootUser, $childUser] = $this->buildTwoLevelUserTree();

        $parentTransaction = $this->createTransaction([
            'from_id' => $childUser->id,
            'amount' => '200.00',
        ]);

        // Create parent fee records
        $this->createTransactionFee($parentTransaction->id, $childUser->id, [
            'profit' => '10.00',
            'actual_profit' => '0',
            'fee' => '6.00',
            'actual_fee' => '0',
        ]);
        $this->createTransactionFee($parentTransaction->id, 0, [
            'profit' => '4.00',
            'actual_profit' => '0',
            'fee' => '0',
            'actual_fee' => '0',
            'account_mode' => null,
        ]);

        $childTransaction = $this->createTransaction([
            'from_id' => $childUser->id,
            'amount' => '100.00',
            'parent_id' => $parentTransaction->id,
        ]);

        // The method reads parent fees, then calls calculator->scaleFeesProportionally
        // Note: $fee->acutal_profit (typo in source) will be null since the column is actual_profit
        $this->calculator->shouldReceive('scaleFeesProportionally')
            ->once()
            ->withArgs(function ($parentFees, $childAmount, $parentAmount) {
                return count($parentFees) === 2;
            })
            ->andReturn([
                ['profit' => '5.00', 'actual_profit' => '0', 'fee' => '3.00', 'actual_fee' => '0'],
                ['profit' => '2.00', 'actual_profit' => '0', 'fee' => '0', 'actual_fee' => '0'],
            ]);

        $this->service->createWithdrawFees($childTransaction, $childUser, false, $parentTransaction);

        $fees = TransactionFee::where('transaction_id', $childTransaction->id)->get();
        $this->assertCount(2, $fees);
    }

    public function test_createWithdrawFees_with_agent_profit_enabled(): void
    {
        // Bind mock FeatureToggleRepository into the container (createWithdrawFees uses app())
        $containerRepo = Mockery::mock(FeatureToggleRepository::class);
        $containerRepo->shouldReceive('enabled')
            ->with(FeatureToggle::AGENT_WITHDRAW_PROFIT)
            ->andReturn(true);
        $this->app->instance(FeatureToggleRepository::class, $containerRepo);

        [$rootUser, $childUser] = $this->buildTwoLevelUserTree(
            User::ROLE_MERCHANT,
            User::ROLE_MERCHANT,
            ['withdraw_fee' => '2.00'],
            ['withdraw_fee' => '3.00']
        );

        $bankName = 'TestBank' . mt_rand(100000, 999999);
        $this->createBank($bankName);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'from_id' => $childUser->id,
            'amount' => '100.00',
            'from_channel_account' => json_encode(['bank_name' => $bankName]),
        ]);

        // allocateWithdrawFees receives [rootFee, childFee] and returns allocations
        $this->calculator->shouldReceive('allocateWithdrawFees')
            ->once()
            ->withArgs(function ($feeAmounts) {
                return count($feeAmounts) === 2;
            })
            ->andReturn([
                ['profit' => '1.00', 'fee' => '0'],
                ['profit' => '0', 'fee' => '4.50'],
            ]);

        // No thirdchannel, so calculateWithdrawSystemProfit gets topLevelFee and null
        $this->calculator->shouldReceive('calculateWithdrawSystemProfit')
            ->once()
            ->andReturn('3.00');

        $this->service->createWithdrawFees($transaction, $childUser);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // 2 user fees (root + child) + 1 system fee = 3
        $this->assertCount(3, $fees);

        // Verify root user fee
        $rootFee = $fees->where('user_id', $rootUser->id)->first();
        $this->assertNotNull($rootFee);
        $this->assertEquals(0, bccomp('1.00', $rootFee->profit, 2));

        // Verify child user fee
        $childFee = $fees->where('user_id', $childUser->id)->first();
        $this->assertNotNull($childFee);
        $this->assertEquals(0, bccomp('4.50', $childFee->fee, 2));

        // Verify system fee (user_id=0, no thirdchannel_id)
        $systemFee = $fees->where('user_id', 0)->first();
        $this->assertNotNull($systemFee);
        $this->assertEquals(0, bccomp('3.00', $systemFee->profit, 2));
    }

    public function test_createWithdrawFees_without_agent_profit(): void
    {
        // AGENT_WITHDRAW_PROFIT disabled -> only endUser in the collection
        $containerRepo = Mockery::mock(FeatureToggleRepository::class);
        $containerRepo->shouldReceive('enabled')
            ->with(FeatureToggle::AGENT_WITHDRAW_PROFIT)
            ->andReturn(false);
        $this->app->instance(FeatureToggleRepository::class, $containerRepo);

        $user = $this->createMerchantWithWallet([], [
            'withdraw_fee' => '2.00',
        ]);

        $bankName = 'TestBank' . mt_rand(100000, 999999);
        $this->createBank($bankName);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'from_id' => $user->id,
            'amount' => '100.00',
            'from_channel_account' => json_encode(['bank_name' => $bankName]),
        ]);

        // Only one user, so allocateWithdrawFees receives array of 1 element
        $this->calculator->shouldReceive('allocateWithdrawFees')
            ->once()
            ->withArgs(function ($feeAmounts) {
                return count($feeAmounts) === 1;
            })
            ->andReturn([
                ['profit' => '0', 'fee' => '3.00'],
            ]);

        $this->calculator->shouldReceive('calculateWithdrawSystemProfit')
            ->once()
            ->andReturn('3.00');

        $this->service->createWithdrawFees($transaction, $user);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // 1 user fee + 1 system fee = 2
        $this->assertCount(2, $fees);

        $userFee = $fees->where('user_id', $user->id)->first();
        $this->assertNotNull($userFee);
        $this->assertEquals(0, bccomp('3.00', $userFee->fee, 2));

        $systemFee = $fees->where('user_id', 0)->whereNull('thirdchannel_id')->first();
        $this->assertNotNull($systemFee);
        $this->assertEquals(0, bccomp('3.00', $systemFee->profit, 2));
    }

    public function test_createWithdrawFees_with_thirdchannel(): void
    {
        if (!Schema::hasColumn('merchant_thirdchannel', 'daifu_fee_percent')) {
            $this->markTestSkipped('daifu_fee_percent column not present in merchant_thirdchannel (pending migration)');
        }

        $containerRepo = Mockery::mock(FeatureToggleRepository::class);
        $containerRepo->shouldReceive('enabled')
            ->with(FeatureToggle::AGENT_WITHDRAW_PROFIT)
            ->andReturn(false);
        $this->app->instance(FeatureToggleRepository::class, $containerRepo);

        $user = $this->createMerchantWithWallet([], [
            'withdraw_fee' => '1.00',
        ]);

        $bankName = 'TestBank' . mt_rand(100000, 999999);
        $this->createBank($bankName);

        $thirdChannel = $this->createThirdChannel();

        $this->createMerchantThirdChannel($thirdChannel->id, $user->id, [
            'daifu_fee_percent' => '0.30',
            'withdraw_fee' => '1.50',
        ]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'from_id' => $user->id,
            'amount' => '100.00',
            'from_channel_account' => json_encode(['bank_name' => $bankName]),
            'thirdchannel_id' => $thirdChannel->id,
        ]);

        $this->calculator->shouldReceive('allocateWithdrawFees')
            ->once()
            ->andReturn([
                ['profit' => '0', 'fee' => '1.50'],
            ]);

        $this->calculator->shouldReceive('calculateThirdChannelFee')
            ->once()
            ->andReturn('1.80');

        $this->calculator->shouldReceive('calculateWithdrawSystemProfit')
            ->once()
            ->andReturn('0.00');

        $this->service->createWithdrawFees($transaction, $user);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // 1 user fee + 1 thirdchannel fee (user_id=0 w/ thirdchannel_id) + 1 system fee = 3
        $this->assertCount(3, $fees);

        // Thirdchannel fee record
        $tcFee = $fees->where('thirdchannel_id', $thirdChannel->id)->first();
        $this->assertNotNull($tcFee);
        $this->assertEquals(0, $tcFee->user_id);
        $this->assertEquals(0, bccomp('1.80', $tcFee->fee, 2));

        // System fee (no thirdchannel_id)
        $systemFee = $fees->where('user_id', 0)->whereNull('thirdchannel_id')->first();
        $this->assertNotNull($systemFee);
    }

    public function test_createWithdrawFees_without_thirdchannel(): void
    {
        $containerRepo = Mockery::mock(FeatureToggleRepository::class);
        $containerRepo->shouldReceive('enabled')
            ->with(FeatureToggle::AGENT_WITHDRAW_PROFIT)
            ->andReturn(false);
        $this->app->instance(FeatureToggleRepository::class, $containerRepo);

        $user = $this->createMerchantWithWallet([], [
            'withdraw_fee' => '2.00',
        ]);

        $bankName = 'TestBank' . mt_rand(100000, 999999);
        $this->createBank($bankName);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'from_id' => $user->id,
            'amount' => '100.00',
            'from_channel_account' => json_encode(['bank_name' => $bankName]),
            'thirdchannel_id' => null,
        ]);

        $this->calculator->shouldReceive('allocateWithdrawFees')
            ->once()
            ->andReturn([
                ['profit' => '0', 'fee' => '3.00'],
            ]);

        // Without thirdchannel, system profit = topLevelFee directly
        $this->calculator->shouldReceive('calculateWithdrawSystemProfit')
            ->once()
            ->withArgs(function ($topLevelFee, $thirdChannelFee) {
                return $thirdChannelFee === null;
            })
            ->andReturn('3.00');

        $this->service->createWithdrawFees($transaction, $user);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // 1 user + 1 system (no thirdchannel fee)
        $this->assertCount(2, $fees);

        // No thirdchannel fee record
        $tcFees = $fees->whereNotNull('thirdchannel_id');
        $this->assertCount(0, $tcFees);

        $systemFee = $fees->where('user_id', 0)->first();
        $this->assertEquals(0, bccomp('3.00', $systemFee->profit, 2));
    }

    public function test_createWithdrawFees_throws_when_wallet_missing(): void
    {
        $containerRepo = Mockery::mock(FeatureToggleRepository::class);
        $containerRepo->shouldReceive('enabled')
            ->with(FeatureToggle::AGENT_WITHDRAW_PROFIT)
            ->andReturn(false);
        $this->app->instance(FeatureToggleRepository::class, $containerRepo);

        // Create user WITHOUT wallet
        $userId = $this->createUser(['role' => User::ROLE_MERCHANT]);
        $user = User::find($userId);

        $bankName = 'TestBank' . mt_rand(100000, 999999);
        $this->createBank($bankName);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_NORMAL_WITHDRAW,
            'from_id' => $user->id,
            'amount' => '100.00',
            'from_channel_account' => json_encode(['bank_name' => $bankName]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Wallet not found');

        $this->service->createWithdrawFees($transaction, $user);
    }

    // ===============================================================
    //  createPaufenTransactionFees()
    // ===============================================================

    public function test_createPaufenTransactionFees_with_thirdchannel(): void
    {
        if (!Schema::hasColumn('merchant_thirdchannel', 'deposit_fee_percent')) {
            $this->markTestSkipped('deposit_fee_percent column not present in merchant_thirdchannel (pending migration)');
        }

        $provider = $this->createProviderWithWallet();

        // Single merchant (needs nested set for ancestorsAndSelf to work)
        $merchantId = $this->createUserWithNestedSet([
            'role' => User::ROLE_MERCHANT,
            'name' => 'Merchant',
        ], 1021, 1022);
        $this->createWallet($merchantId);
        $merchant = User::find($merchantId);

        $channelGroup = $this->createChannelGroup();

        // Create merchant user channel (needed for merchant fee calculation)
        $this->createUserChannel($merchant->id, $channelGroup->id, [
            'fee_percent' => '3.00',
        ]);

        $thirdChannel = $this->createThirdChannel();

        $this->createMerchantThirdChannel($thirdChannel->id, $merchant->id);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'from_id' => $provider->id,
            'to_id' => $merchant->id,
            'amount' => '100.00',
            'floating_amount' => '98.00',
            'thirdchannel_id' => $thirdChannel->id,
        ]);

        // Mock merchant fee allocations
        $this->calculator->shouldReceive('allocateMerchantFees')
            ->once()
            ->andReturn([
                ['profit' => '0', 'fee' => '3.00'],
            ]);

        // Mock system profit calculation
        $this->calculator->shouldReceive('calculatePaufenSystemProfit')
            ->once()
            ->andReturn('1.50');

        $this->service->createPaufenTransactionFees($transaction, $channelGroup);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // thirdchannel fee + merchant fee + system fee = 3
        $this->assertCount(3, $fees);

        // Thirdchannel fee record
        $tcFee = $fees->where('thirdchannel_id', $thirdChannel->id)->first();
        $this->assertNotNull($tcFee);
        $this->assertEquals(0, $tcFee->user_id);

        // Merchant fee
        $merchantFee = $fees->where('user_id', $merchant->id)->first();
        $this->assertNotNull($merchantFee);
        $this->assertEquals(0, bccomp('3.00', $merchantFee->fee, 2));

        // System fee
        $systemFee = $fees->where('user_id', 0)->whereNull('thirdchannel_id')->first();
        $this->assertNotNull($systemFee);
        $this->assertEquals(0, bccomp('1.50', $systemFee->profit, 2));
    }

    public function test_createPaufenTransactionFees_without_cancelPaufen(): void
    {
        $this->rebuildServiceWithCancelPaufen(false);

        // Single provider (no nested set parent)
        $providerId = $this->createUserWithNestedSet([
            'role' => User::ROLE_PROVIDER,
            'name' => 'Provider',
        ], 1031, 1032);
        $this->createWallet($providerId);
        $provider = User::find($providerId);

        // Single merchant (no nested set parent)
        $merchantId = $this->createUserWithNestedSet([
            'role' => User::ROLE_MERCHANT,
            'name' => 'Merchant',
        ], 1035, 1036);
        $this->createWallet($merchantId);
        $merchant = User::find($merchantId);

        $channelGroup = $this->createChannelGroup();

        // Provider user channel
        $this->createUserChannel($provider->id, $channelGroup->id, [
            'fee_percent' => '2.00',
        ]);

        // Merchant user channel
        $this->createUserChannel($merchant->id, $channelGroup->id, [
            'fee_percent' => '3.00',
        ]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'from_id' => $provider->id,
            'to_id' => $merchant->id,
            'amount' => '100.00',
            'floating_amount' => '99.00',
            'thirdchannel_id' => null,
        ]);

        // Provider fees allocation
        $this->calculator->shouldReceive('allocateProviderFees')
            ->once()
            ->with('99.00', ['2.00'])
            ->andReturn([
                ['profit' => '1.98', 'fee' => '97.02'],
            ]);

        // Merchant fees allocation
        $this->calculator->shouldReceive('allocateMerchantFees')
            ->once()
            ->with('100.00', ['3.00'])
            ->andReturn([
                ['profit' => '0', 'fee' => '3.00'],
            ]);

        // System profit
        $this->calculator->shouldReceive('calculatePaufenSystemProfit')
            ->once()
            ->with('100.00', '99.00', '3.00', '2.00')
            ->andReturn('0.00');

        $this->service->createPaufenTransactionFees($transaction, $channelGroup);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // provider fee + merchant fee + system fee = 3
        $this->assertCount(3, $fees);

        // Provider fee
        $providerFee = $fees->where('user_id', $provider->id)->first();
        $this->assertNotNull($providerFee);
        $this->assertEquals(0, bccomp('1.98', $providerFee->profit, 2));

        // Merchant fee
        $merchantFee = $fees->where('user_id', $merchant->id)->first();
        $this->assertNotNull($merchantFee);
        $this->assertEquals(0, bccomp('3.00', $merchantFee->fee, 2));

        // System fee
        $systemFee = $fees->where('user_id', 0)->first();
        $this->assertNotNull($systemFee);
    }

    public function test_createPaufenTransactionFees_with_cancelPaufen(): void
    {
        $this->rebuildServiceWithCancelPaufen(true);

        $provider = $this->createProviderWithWallet();

        // Single merchant (no nested set parent)
        $merchantId = $this->createUserWithNestedSet([
            'role' => User::ROLE_MERCHANT,
            'name' => 'Merchant',
        ], 1041, 1042);
        $this->createWallet($merchantId);
        $merchant = User::find($merchantId);

        $channelGroup = $this->createChannelGroup();

        // Merchant user channel
        $this->createUserChannel($merchant->id, $channelGroup->id, [
            'fee_percent' => '3.00',
        ]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'from_id' => $provider->id,
            'to_id' => $merchant->id,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'thirdchannel_id' => null,
        ]);

        // cancelPaufen=true -> no provider fees, allocateProviderFees should NOT be called
        $this->calculator->shouldNotReceive('allocateProviderFees');

        // Merchant fees allocation
        $this->calculator->shouldReceive('allocateMerchantFees')
            ->once()
            ->with('100.00', ['3.00'])
            ->andReturn([
                ['profit' => '0', 'fee' => '3.00'],
            ]);

        // System profit - providerFeePercentSet[0] defaults to 0 when cancelPaufen
        $this->calculator->shouldReceive('calculatePaufenSystemProfit')
            ->once()
            ->with('100.00', '100.00', '3.00', 0)
            ->andReturn('3.00');

        $this->service->createPaufenTransactionFees($transaction, $channelGroup);

        $fees = TransactionFee::where('transaction_id', $transaction->id)->get();

        // Only merchant fee + system fee = 2 (no provider fee)
        $this->assertCount(2, $fees);

        // No provider fee
        $providerFee = $fees->where('user_id', $provider->id)->first();
        $this->assertNull($providerFee);

        // Merchant fee
        $merchantFee = $fees->where('user_id', $merchant->id)->first();
        $this->assertNotNull($merchantFee);

        // System fee
        $systemFee = $fees->where('user_id', 0)->first();
        $this->assertNotNull($systemFee);
        $this->assertEquals(0, bccomp('3.00', $systemFee->profit, 2));
    }

    public function test_createPaufenTransactionFees_throws_when_provider_user_channel_missing(): void
    {
        $this->rebuildServiceWithCancelPaufen(false);

        // Single provider without userChannel
        $providerId = $this->createUserWithNestedSet([
            'role' => User::ROLE_PROVIDER,
            'name' => 'Provider',
        ], 1051, 1052);
        $this->createWallet($providerId);
        $provider = User::find($providerId);

        // Single merchant with userChannel
        $merchantId = $this->createUserWithNestedSet([
            'role' => User::ROLE_MERCHANT,
            'name' => 'Merchant',
        ], 1055, 1056);
        $this->createWallet($merchantId);
        $merchant = User::find($merchantId);

        $channelGroup = $this->createChannelGroup();

        // Only create merchant user channel - provider has NO user channel
        $this->createUserChannel($merchant->id, $channelGroup->id, [
            'fee_percent' => '3.00',
        ]);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'from_id' => $provider->id,
            'to_id' => $merchant->id,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'thirdchannel_id' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider user channel not found');

        $this->service->createPaufenTransactionFees($transaction, $channelGroup);
    }

    public function test_createPaufenTransactionFees_throws_when_merchant_user_channel_missing(): void
    {
        $this->rebuildServiceWithCancelPaufen(true); // cancelPaufen=true to skip provider fees

        $provider = $this->createProviderWithWallet();

        // Single merchant WITHOUT user channel
        $merchantId = $this->createUserWithNestedSet([
            'role' => User::ROLE_MERCHANT,
            'name' => 'Merchant',
        ], 1061, 1062);
        $this->createWallet($merchantId);
        $merchant = User::find($merchantId);

        $channelGroup = $this->createChannelGroup();

        // NO merchant user channel created

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'from_id' => $provider->id,
            'to_id' => $merchant->id,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'thirdchannel_id' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Merchant user channel not found');

        $this->service->createPaufenTransactionFees($transaction, $channelGroup);
    }

    // ===============================================================
    //  createSeparateWithdrawFees (tested via createWithdrawFees)
    // ===============================================================

    public function test_createSeparateWithdrawFees_scales_parent_fees(): void
    {
        $user = $this->createMerchantWithWallet();

        $parentTransaction = $this->createTransaction([
            'from_id' => $user->id,
            'amount' => '200.00',
        ]);

        // Create 2 parent fee records: user + system
        $this->createTransactionFee($parentTransaction->id, $user->id, [
            'profit' => '0',
            'actual_profit' => '0',
            'fee' => '6.00',
            'actual_fee' => '0',
        ]);
        $this->createTransactionFee($parentTransaction->id, 0, [
            'profit' => '6.00',
            'actual_profit' => '0',
            'fee' => '0',
            'actual_fee' => '0',
            'account_mode' => null,
        ]);

        $childTransaction = $this->createTransaction([
            'from_id' => $user->id,
            'amount' => '100.00',
            'parent_id' => $parentTransaction->id,
        ]);

        // scaleFeesProportionally receives parentFees array, child amount, parent amount
        // Note: the source code has a typo ($fee->acutal_profit), which means null is passed
        $this->calculator->shouldReceive('scaleFeesProportionally')
            ->once()
            ->withArgs(function ($parentFees, $childAmount, $parentAmount) {
                return count($parentFees) === 2;
            })
            ->andReturn([
                ['profit' => '0', 'actual_profit' => '0', 'fee' => '3.00', 'actual_fee' => '0'],
                ['profit' => '3.00', 'actual_profit' => '0', 'fee' => '0', 'actual_fee' => '0'],
            ]);

        $this->service->createWithdrawFees($childTransaction, $user, false, $parentTransaction);

        $fees = TransactionFee::where('transaction_id', $childTransaction->id)->get();
        $this->assertCount(2, $fees);

        // Child user fee should be scaled (half of parent)
        $userFee = $fees->where('user_id', $user->id)->first();
        $this->assertNotNull($userFee);
        $this->assertEquals(0, bccomp('3.00', $userFee->fee, 2));

        // System fee should be scaled
        $systemFee = $fees->where('user_id', 0)->first();
        $this->assertNotNull($systemFee);
        $this->assertEquals(0, bccomp('3.00', $systemFee->profit, 2));
    }

    public function test_createSeparateWithdrawFees_preserves_user_and_account_mode(): void
    {
        $merchantCredit = $this->createMerchantWithWallet(
            ['account_mode' => User::ACCOUNT_MODE_CREDIT],
            []
        );

        $parentTransaction = $this->createTransaction([
            'from_id' => $merchantCredit->id,
            'amount' => '200.00',
        ]);

        // Parent fee with specific account_mode
        $this->createTransactionFee($parentTransaction->id, $merchantCredit->id, [
            'account_mode' => User::ACCOUNT_MODE_CREDIT,
            'profit' => '0',
            'actual_profit' => '0',
            'fee' => '8.00',
            'actual_fee' => '0',
        ]);
        $this->createTransactionFee($parentTransaction->id, 0, [
            'account_mode' => null,
            'profit' => '8.00',
            'actual_profit' => '0',
            'fee' => '0',
            'actual_fee' => '0',
        ]);

        $childTransaction = $this->createTransaction([
            'from_id' => $merchantCredit->id,
            'amount' => '50.00',
            'parent_id' => $parentTransaction->id,
        ]);

        $this->calculator->shouldReceive('scaleFeesProportionally')
            ->once()
            ->andReturn([
                ['profit' => '0', 'actual_profit' => '0', 'fee' => '2.00', 'actual_fee' => '0'],
                ['profit' => '2.00', 'actual_profit' => '0', 'fee' => '0', 'actual_fee' => '0'],
            ]);

        $this->service->createWithdrawFees($childTransaction, $merchantCredit, false, $parentTransaction);

        $fees = TransactionFee::where('transaction_id', $childTransaction->id)->get();
        $this->assertCount(2, $fees);

        // User fee preserves account_mode from parent fee
        $userFee = $fees->where('user_id', $merchantCredit->id)->first();
        $this->assertNotNull($userFee);
        $this->assertEquals(User::ACCOUNT_MODE_CREDIT, $userFee->account_mode);

        // System fee preserves null account_mode from parent fee
        $systemFee = $fees->where('user_id', 0)->first();
        $this->assertNotNull($systemFee);
        $this->assertNull($systemFee->account_mode);

        // User_id is preserved from parent fee records
        $this->assertEquals($merchantCredit->id, $userFee->user_id);
        $this->assertEquals(0, $systemFee->user_id);
    }
}
