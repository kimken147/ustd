<?php

namespace Tests\Unit\Services\Withdraw;

use App\Models\FeatureToggle;
use App\Models\MerchantThirdChannel;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionStatusService;
use App\Services\Withdraw\ThirdChannelDispatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class ThirdChannelDispatcherTest extends TestCase
{
    use DatabaseTransactions;

    private ThirdChannelDispatcher $dispatcher;
    private $featureToggleRepository;
    private $statusService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureToggleRepository = Mockery::mock(FeatureToggleRepository::class);
        $this->statusService = Mockery::mock(TransactionStatusService::class);

        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL)
            ->andReturn(false)
            ->byDefault();

        $this->dispatcher = new ThirdChannelDispatcher(
            $this->featureToggleRepository,
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

    private function createThirdChannel(array $overrides = []): ThirdChannel
    {
        $defaults = [
            'name' => 'TestChannel',
            'class' => 'TestChannelClass',
            'status' => ThirdChannel::STATUS_ENABLE,
            'type' => ThirdChannel::TYPE_DEPOSIT_WITHDRAW,
            'custom_url' => 'example.com',
        ];

        // Only include columns that exist in the test DB
        $optionalColumns = [
            'balance' => '100000.00',
            'merchant_id' => 'TEST_MERCHANT',
            'auto_daifu_threshold' => '99999999.00',
            'auto_daifu_threshold_min' => '0.00',
        ];
        foreach ($optionalColumns as $col => $val) {
            if (Schema::hasColumn('thirdchannel', $col)) {
                $defaults[$col] = $val;
            }
        }

        $merged = array_merge($defaults, $overrides);

        // Filter out any keys for columns that don't exist in the test DB
        $columns = Schema::getColumnListing('thirdchannel');
        $merged = array_intersect_key($merged, array_flip($columns));

        $id = DB::table('thirdchannel')->insertGetId($merged);

        return ThirdChannel::find($id);
    }

    private function createMerchantThirdChannel(int $ownerId, int $thirdChannelId, array $overrides = []): MerchantThirdChannel
    {
        $defaults = [
            'owner_id' => $ownerId,
            'thirdchannel_id' => $thirdChannelId,
            'daifu_min' => '10.00',
            'daifu_max' => '50000.00',
            'deposit_min' => '10.00',
            'deposit_max' => '50000.00',
        ];

        $id = DB::table('merchant_thirdchannel')->insertGetId(array_merge($defaults, $overrides));

        return MerchantThirdChannel::find($id);
    }

    // ===============================================================
    //  dispatch()
    // ===============================================================

    public function test_dispatch_calls_local_fallback_when_third_channel_disabled(): void
    {
        $merchantId = $this->createUser([
            'role' => User::ROLE_MERCHANT,
            'third_channel_enable' => false,
        ]);
        $merchant = User::find($merchantId);

        $fallbackCalled = false;
        $successCalled = false;

        $transaction = $this->createTransaction(['from_id' => $merchant->id]);

        $result = $this->dispatcher->dispatch(
            $merchant,
            '500.00',
            'ORD123456',
            ['bank_name' => 'Test Bank'],
            function () use (&$successCalled) {
                $successCalled = true;
            },
            function () use (&$fallbackCalled, $transaction) {
                $fallbackCalled = true;
                return $transaction;
            },
        );

        $this->assertTrue($fallbackCalled);
        $this->assertFalse($successCalled);
        $this->assertEquals($transaction->id, $result->id);

        // No TransactionNote should be created (immediate fallback, no channel lookup)
        $noteCount = TransactionNote::where('transaction_id', $transaction->id)->count();
        $this->assertEquals(0, $noteCount);

        // statusService->markAsFailed should NOT have been called
        $this->statusService->shouldNotHaveReceived('markAsFailed');
    }

    public function test_dispatch_calls_local_fallback_and_adds_note_when_no_channels(): void
    {
        $merchantId = $this->createUser([
            'role' => User::ROLE_MERCHANT,
            'third_channel_enable' => true,
        ]);
        $merchant = User::find($merchantId);

        // No MerchantThirdChannel records for this merchant -> getAvailableChannels returns empty

        $fallbackCalled = false;
        $successCalled = false;

        $transaction = $this->createTransaction(['from_id' => $merchant->id]);

        $result = $this->dispatcher->dispatch(
            $merchant,
            '500.00',
            'ORD123456',
            ['bank_name' => 'Test Bank'],
            function () use (&$successCalled) {
                $successCalled = true;
            },
            function () use (&$fallbackCalled, $transaction) {
                $fallbackCalled = true;
                return $transaction;
            },
        );

        $this->assertTrue($fallbackCalled);
        $this->assertFalse($successCalled);
        $this->assertEquals($transaction->id, $result->id);

        // TransactionNote should be created with the expected message
        $note = TransactionNote::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($note);
        $this->assertEquals('无符合当前代付金额的三方可用，请调整限额设定', $note->note);
        $this->assertEquals(0, $note->user_id);
    }

    public function test_dispatch_calls_local_fallback_when_no_channels_in_threshold(): void
    {
        if (!Schema::hasColumn('thirdchannel', 'auto_daifu_threshold')) {
            $this->markTestSkipped('auto_daifu_threshold column not present in test DB');
        }

        $merchantId = $this->createUser([
            'role' => User::ROLE_MERCHANT,
            'third_channel_enable' => true,
        ]);
        $merchant = User::find($merchantId);

        // Create a ThirdChannel that passes getAvailableChannels (status=enabled, type!=deposit_only)
        // but fails filterByThreshold (threshold range doesn't include the amount)
        $thirdChannel = $this->createThirdChannel([
            'status' => ThirdChannel::STATUS_ENABLE,
            'type' => ThirdChannel::TYPE_DEPOSIT_WITHDRAW,
            'auto_daifu_threshold' => '100.00',       // max threshold
            'auto_daifu_threshold_min' => '50.00',     // min threshold
        ]);

        // MerchantThirdChannel with daifu range covering our amount (500)
        $this->createMerchantThirdChannel($merchant->id, $thirdChannel->id, [
            'daifu_min' => '10.00',
            'daifu_max' => '50000.00',
        ]);

        $fallbackCalled = false;
        $successCalled = false;

        $transaction = $this->createTransaction(['from_id' => $merchant->id]);

        // Amount 500 is within daifu_min/daifu_max (10-50000)
        // but outside auto_daifu_threshold_min/auto_daifu_threshold (50-100)
        $result = $this->dispatcher->dispatch(
            $merchant,
            '500.00',
            'ORD123456',
            ['bank_name' => 'Test Bank'],
            function () use (&$successCalled) {
                $successCalled = true;
            },
            function () use (&$fallbackCalled, $transaction) {
                $fallbackCalled = true;
                return $transaction;
            },
        );

        $this->assertTrue($fallbackCalled);
        $this->assertFalse($successCalled);
        $this->assertEquals($transaction->id, $result->id);

        // TransactionNote should have the threshold-specific message
        $note = TransactionNote::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($note);
        $this->assertEquals('无自动推送门槛内的三方可用，请手动推送', $note->note);
        $this->assertEquals(0, $note->user_id);
    }

    public function test_dispatch_marks_as_failed_when_feature_enabled(): void
    {
        // Override the default to return true for this feature toggle
        $this->featureToggleRepository = Mockery::mock(FeatureToggleRepository::class);
        $this->featureToggleRepository
            ->shouldReceive('enabled')
            ->with(FeatureToggle::IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL)
            ->andReturn(true);

        $this->statusService = Mockery::mock(TransactionStatusService::class);

        $this->dispatcher = new ThirdChannelDispatcher(
            $this->featureToggleRepository,
            $this->statusService,
        );

        $merchantId = $this->createUser([
            'role' => User::ROLE_MERCHANT,
            'third_channel_enable' => true,
        ]);
        $merchant = User::find($merchantId);

        // No MerchantThirdChannel records -> getAvailableChannels returns empty
        $transaction = $this->createTransaction(['from_id' => $merchant->id]);

        $this->statusService->shouldReceive('markAsFailed')
            ->once()
            ->with(
                Mockery::on(fn ($t) => $t->id === $transaction->id),
                null,
                '无符合当前代付金额的三方可用，请调整限额设定',
                false
            );

        $result = $this->dispatcher->dispatch(
            $merchant,
            '500.00',
            'ORD123456',
            ['bank_name' => 'Test Bank'],
            function () {
                $this->fail('onThirdChannelSuccess should not be called');
            },
            function () use ($transaction) {
                return $transaction;
            },
        );

        $this->assertEquals($transaction->id, $result->id);
    }

    public function test_dispatch_skips_mark_as_failed_when_feature_disabled(): void
    {
        // Default mock already returns false for IF_THIRDCHANNEL_DAIFU_FIAL_THAN_ORDER_FAIL

        $merchantId = $this->createUser([
            'role' => User::ROLE_MERCHANT,
            'third_channel_enable' => true,
        ]);
        $merchant = User::find($merchantId);

        // No MerchantThirdChannel records -> getAvailableChannels returns empty
        $transaction = $this->createTransaction(['from_id' => $merchant->id]);

        $this->statusService->shouldNotReceive('markAsFailed');

        $result = $this->dispatcher->dispatch(
            $merchant,
            '500.00',
            'ORD123456',
            ['bank_name' => 'Test Bank'],
            function () {
                $this->fail('onThirdChannelSuccess should not be called');
            },
            function () use ($transaction) {
                return $transaction;
            },
        );

        $this->assertEquals($transaction->id, $result->id);

        // TransactionNote should still be created even though markAsFailed is skipped
        $note = TransactionNote::where('transaction_id', $transaction->id)->first();
        $this->assertNotNull($note);
        $this->assertEquals('无符合当前代付金额的三方可用，请调整限额设定', $note->note);
    }
}
