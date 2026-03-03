<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MarkPaufenTransactionPayingTimedOut;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\UserChannelAccountUtil;
use App\Utils\WalletUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class MarkPaufenTransactionPayingTimedOutTest extends TestCase
{
    use DatabaseTransactions;

    private $mockWallet;
    private $mockBcMath;
    private $mockNotificationUtil;
    private $mockFeatureToggle;
    private $mockUserChannelAccountUtil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWallet = Mockery::mock(WalletUtil::class);
        $this->mockBcMath = Mockery::mock(BCMathUtil::class);
        $this->mockNotificationUtil = Mockery::mock(NotificationUtil::class);
        $this->mockFeatureToggle = Mockery::mock(FeatureToggleRepository::class);
        $this->mockUserChannelAccountUtil = Mockery::mock(UserChannelAccountUtil::class);

        $this->app->instance(WalletUtil::class, $this->mockWallet);
        $this->app->instance(BCMathUtil::class, $this->mockBcMath);
        $this->app->instance(NotificationUtil::class, $this->mockNotificationUtil);
        $this->app->instance(FeatureToggleRepository::class, $this->mockFeatureToggle);
        $this->app->instance(UserChannelAccountUtil::class, $this->mockUserChannelAccountUtil);
    }

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
            'balance_limit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $user = $this->createUser();
        $userId = $user->getKey();

        // Create a wallet for the user
        $walletId = DB::table('wallets')->insertGetId([
            'user_id' => $userId,
            'status' => 1,
            'balance' => '1000.00',
            'frozen_balance' => '0.00',
            'withdraw_fee' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('transactions')->insertGetId(array_merge([
            'from_id' => $userId,
            'from_wallet_id' => $walletId,
            'to_id' => $userId,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => 0,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'channel_code' => 'BANK',
            'order_number' => 'ORD_' . uniqid(),
            'system_order_number' => 'SYS_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return Transaction::find($id);
    }

    /**
     * Helper to set default mock expectations for the "happy path" after DB update.
     */
    private function setupDefaultPostUpdateMocks(): void
    {
        $this->mockFeatureToggle->shouldReceive('enabled')
            ->with(FeatureToggle::CANCEL_PAUFEN_MECHANISM)
            ->andReturn(true)
            ->byDefault();

        $this->mockFeatureToggle->shouldReceive('enabled')
            ->with(FeatureToggle::NOTIFY_NON_SUCCESS_USER_CHANNEL_ACCOUNT, true)
            ->andReturn(false)
            ->byDefault();

        $this->mockFeatureToggle->shouldReceive('enabled')
            ->with(FeatureToggle::DISABLE_TRANSACTION_IF_PAYING_TIMEOUT, true)
            ->andReturn(false)
            ->byDefault();
    }

    public function test_handle_skips_when_transaction_not_found(): void
    {
        $job = new MarkPaufenTransactionPayingTimedOut(999999);
        $job->handle(
            $this->mockWallet,
            $this->mockBcMath,
            $this->mockNotificationUtil,
            $this->mockFeatureToggle,
            $this->mockUserChannelAccountUtil
        );

        // No exception means it returned early successfully
        $this->assertTrue(true);
    }

    public function test_handle_skips_when_status_is_not_paying(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_SUCCESS,
        ]);

        $job = new MarkPaufenTransactionPayingTimedOut($transaction->getKey());
        $job->handle(
            $this->mockWallet,
            $this->mockBcMath,
            $this->mockNotificationUtil,
            $this->mockFeatureToggle,
            $this->mockUserChannelAccountUtil
        );

        $transaction->refresh();
        $this->assertEquals(
            Transaction::STATUS_SUCCESS,
            $transaction->status,
            'Status should remain unchanged when not in PAYING state'
        );
    }

    public function test_handle_updates_status_to_paying_timed_out(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_PAYING,
        ]);

        $this->setupDefaultPostUpdateMocks();

        $job = new MarkPaufenTransactionPayingTimedOut($transaction->getKey());
        $job->handle(
            $this->mockWallet,
            $this->mockBcMath,
            $this->mockNotificationUtil,
            $this->mockFeatureToggle,
            $this->mockUserChannelAccountUtil
        );

        $transaction->refresh();
        $this->assertEquals(
            Transaction::STATUS_PAYING_TIMED_OUT,
            $transaction->status,
            'Status should be updated to PAYING_TIMED_OUT'
        );
    }

    public function test_handle_calls_withdrawRollback_when_cancel_order_disabled(): void
    {
        $user = $this->createUser([
            'cancel_order_enable' => false,
        ]);
        $userId = $user->getKey();

        $walletId = DB::table('wallets')->insertGetId([
            'user_id' => $userId,
            'status' => 1,
            'balance' => '1000.00',
            'frozen_balance' => '0.00',
            'withdraw_fee' => '0.00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transactionId = DB::table('transactions')->insertGetId([
            'from_id' => $userId,
            'from_wallet_id' => $walletId,
            'to_id' => $userId,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => 0,
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'channel_code' => 'BANK',
            'order_number' => 'ORD_' . uniqid(),
            'system_order_number' => 'SYS_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $transaction = Transaction::find($transactionId);

        // cancel_order_enable is false on user AND CANCEL_PAUFEN_MECHANISM is false
        $this->mockFeatureToggle->shouldReceive('enabled')
            ->with(FeatureToggle::CANCEL_PAUFEN_MECHANISM)
            ->andReturn(false);

        $this->mockWallet->shouldReceive('withdrawRollback')
            ->once()
            ->withArgs(function ($wallet, $amount, $orderNumber, $type) use ($transaction) {
                return $amount == $transaction->floating_amount
                    && $orderNumber == $transaction->system_order_number
                    && $type == 'transaction';
            });

        $this->mockFeatureToggle->shouldReceive('enabled')
            ->with(FeatureToggle::NOTIFY_NON_SUCCESS_USER_CHANNEL_ACCOUNT, true)
            ->andReturn(false);

        $this->mockFeatureToggle->shouldReceive('enabled')
            ->with(FeatureToggle::DISABLE_TRANSACTION_IF_PAYING_TIMEOUT, true)
            ->andReturn(false);

        $job = new MarkPaufenTransactionPayingTimedOut($transaction->getKey());
        $job->handle(
            $this->mockWallet,
            $this->mockBcMath,
            $this->mockNotificationUtil,
            $this->mockFeatureToggle,
            $this->mockUserChannelAccountUtil
        );

        $transaction->refresh();
        $this->assertEquals(
            Transaction::STATUS_PAYING_TIMED_OUT,
            $transaction->status,
            'Status should be updated to PAYING_TIMED_OUT'
        );
        $this->assertNotNull(
            $transaction->refunded_at,
            'refunded_at should be set when cancel_order is disabled'
        );
    }
}
