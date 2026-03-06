<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Models\Wallet;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\DaiFuService;
use App\Utils\TransactionMutator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class DaiFuServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $transactionMutator;
    private $featureToggleRepo;
    private DaiFuService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transactionMutator = Mockery::mock(TransactionMutator::class);
        $this->featureToggleRepo = Mockery::mock(FeatureToggleRepository::class);

        $this->featureToggleRepo->shouldReceive('enabled')->andReturn(false)->byDefault();
        $this->featureToggleRepo->shouldReceive('valueOf')->andReturn(0)->byDefault();

        $this->service = new DaiFuService(
            $this->transactionMutator,
            $this->featureToggleRepo
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
            'role' => User::ROLE_PROVIDER,
            'status' => User::STATUS_ENABLE,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
            'deposit_enable' => true,
            'paufen_deposit_enable' => true,
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
            'agency_withdraw_min_amount' => 0,
            'agency_withdraw_max_amount' => 0,
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
            'from_channel_account' => json_encode([]),
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

    private function createUserChannelAccount(int $userId, array $overrides = []): UserChannelAccount
    {
        $data = array_merge([
            'user_id' => $userId,
            'status' => UserChannelAccount::STATUS_ONLINE,
            'type' => UserChannelAccount::TYPE_WITHDRAW,
            'account' => 'ACC_' . uniqid(),
            'detail' => json_encode(['bank_name' => 'Test Bank']),
            'balance' => '5000.00',
            'daily_status' => UserChannelAccount::DAILY_STATUS_DISABLE,
            'daily_limit' => 0,
            'daily_total' => '0.00',
            'monthly_status' => UserChannelAccount::MONTHLY_STATUS_DISABLE,
            'monthly_limit' => 0,
            'monthly_total' => '0.00',
            'fee_percent' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        if (Schema::hasColumn('user_channel_accounts', 'balance_limit') && !array_key_exists('balance_limit', $overrides)) {
            $data['balance_limit'] = 0;
        }

        if (Schema::hasColumn('user_channel_accounts', 'name') && !array_key_exists('name', $overrides)) {
            $data['name'] = 'test_account_' . uniqid();
        }

        if (Schema::hasColumn('user_channel_accounts', 'channel_code') && !array_key_exists('channel_code', $overrides)) {
            $data['channel_code'] = 'BANK';
        }

        if (Schema::hasColumn('user_channel_accounts', 'is_auto') && !array_key_exists('is_auto', $overrides)) {
            $data['is_auto'] = true;
        }

        $id = DB::table('user_channel_accounts')->insertGetId($data);

        return UserChannelAccount::find($id);
    }

    // ---------------------------------------------------------------
    //  checkAutoIsValid() tests
    // ---------------------------------------------------------------

    public function test_checkAutoIsValid_returns_true_when_all_conditions_met(): void
    {
        config(['app.region' => 'tw']);

        $this->featureToggleRepo
            ->shouldReceive('enabled')
            ->with(FeatureToggle::AUTO_DAIFU, true)
            ->once()
            ->andReturn(true);

        $this->featureToggleRepo
            ->shouldReceive('valueOf')
            ->with(FeatureToggle::AUTO_DAIFU)
            ->once()
            ->andReturn(2);

        $result = $this->service->checkAutoIsValid('tw');

        $this->assertTrue($result);
    }

    public function test_checkAutoIsValid_returns_false_when_region_mismatch(): void
    {
        config(['app.region' => 'tw']);

        $result = $this->service->checkAutoIsValid('cn');

        $this->assertFalse($result);
    }

    public function test_checkAutoIsValid_returns_false_when_feature_disabled(): void
    {
        config(['app.region' => 'tw']);

        $this->featureToggleRepo
            ->shouldReceive('enabled')
            ->with(FeatureToggle::AUTO_DAIFU, true)
            ->once()
            ->andReturn(false);

        $result = $this->service->checkAutoIsValid('tw');

        $this->assertFalse($result);
    }

    public function test_checkAutoIsValid_returns_false_when_value_is_not_2(): void
    {
        config(['app.region' => 'tw']);

        $this->featureToggleRepo
            ->shouldReceive('enabled')
            ->with(FeatureToggle::AUTO_DAIFU, true)
            ->once()
            ->andReturn(true);

        $this->featureToggleRepo
            ->shouldReceive('valueOf')
            ->with(FeatureToggle::AUTO_DAIFU)
            ->once()
            ->andReturn(1);

        $result = $this->service->checkAutoIsValid('tw');

        $this->assertFalse($result);
    }

    // ---------------------------------------------------------------
    //  execute() tests
    // ---------------------------------------------------------------

    public function test_execute_does_nothing_when_no_matching_accounts(): void
    {
        $this->transactionMutator->shouldNotReceive('paufenDepositToAccount');

        $this->service->execute('NONEXISTENT_CHANNEL');
    }

    public function test_execute_skips_user_when_deposit_not_enabled(): void
    {
        $user = $this->createUser(['deposit_enable' => false]);
        $this->createUserChannelAccount($user->id, ['channel_code' => 'BANK']);

        $this->transactionMutator->shouldNotReceive('paufenDepositToAccount');

        $this->service->execute('BANK');
    }

    public function test_execute_skips_user_when_paufen_deposit_not_enabled(): void
    {
        $user = $this->createUser(['paufen_deposit_enable' => false]);
        $this->createUserChannelAccount($user->id, ['channel_code' => 'BANK']);

        $this->transactionMutator->shouldNotReceive('paufenDepositToAccount');

        $this->service->execute('BANK');
    }

    public function test_execute_skips_when_no_matching_transaction(): void
    {
        $user = $this->createUser([
            'deposit_enable' => true,
            'paufen_deposit_enable' => true,
        ]);
        $this->createWallet($user->id);
        $this->createUserChannelAccount($user->id, ['channel_code' => 'BANK']);

        // Bind mock FeatureToggleRepository in container for getRestBalance()
        $this->app->instance(FeatureToggleRepository::class, $this->featureToggleRepo);

        // No transactions exist in STATUS_MATCHING
        $this->transactionMutator->shouldNotReceive('paufenDepositToAccount');

        $this->service->execute('BANK');
    }

    public function test_execute_calls_paufenDepositToAccount_when_match_found(): void
    {
        $user = $this->createUser([
            'deposit_enable' => true,
            'paufen_deposit_enable' => true,
        ]);
        $this->createWallet($user->id, [
            'agency_withdraw_min_amount' => 0,
            'agency_withdraw_max_amount' => 0,
        ]);
        $this->createUserChannelAccount($user->id, ['channel_code' => 'BANK']);

        // Bind mock FeatureToggleRepository in container for getRestBalance()
        $this->app->instance(FeatureToggleRepository::class, $this->featureToggleRepo);

        $transaction = $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_MATCHING,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'to_id' => null,
            'locked_at' => null,
            'created_at' => now(),
        ]);

        $this->transactionMutator
            ->shouldReceive('paufenDepositToAccount')
            ->once()
            ->withArgs(function ($account, $matchedTransaction) use ($transaction) {
                return $account instanceof UserChannelAccount
                    && (int) $matchedTransaction->id === (int) $transaction->id;
            });

        $this->service->execute('BANK');
    }

    public function test_execute_skips_locked_transactions(): void
    {
        $user = $this->createUser([
            'deposit_enable' => true,
            'paufen_deposit_enable' => true,
        ]);
        $this->createWallet($user->id, [
            'agency_withdraw_min_amount' => 0,
            'agency_withdraw_max_amount' => 0,
        ]);
        $this->createUserChannelAccount($user->id, ['channel_code' => 'BANK']);

        // Bind mock FeatureToggleRepository in container for getRestBalance()
        $this->app->instance(FeatureToggleRepository::class, $this->featureToggleRepo);

        // Create a transaction that is locked (locked_at is set)
        $this->createTransaction([
            'type' => Transaction::TYPE_PAUFEN_WITHDRAW,
            'status' => Transaction::STATUS_MATCHING,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'to_id' => null,
            'locked_at' => now(),
            'created_at' => now(),
        ]);

        $this->transactionMutator->shouldNotReceive('paufenDepositToAccount');

        $this->service->execute('BANK');
    }
}
