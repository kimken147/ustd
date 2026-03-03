<?php

namespace Tests\Unit\Utils;

use App\Models\UserChannelAccount;
use App\Utils\BCMathUtil;
use App\Utils\UserChannelAccountUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserChannelAccountUtilTest extends TestCase
{
    use DatabaseTransactions;

    private BCMathUtil $bcMath;
    private UserChannelAccountUtil $util;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bcMath = new BCMathUtil();
        $this->util = new UserChannelAccountUtil($this->bcMath);

        if (!Schema::hasColumn('user_channel_accounts', 'daily_total')) {
            $this->markTestSkipped('user_channel_accounts table missing required columns');
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createUserChannelAccount(array $overrides = []): UserChannelAccount
    {
        $userId = DB::table('users')->insertGetId([
            'name' => 'test_user',
            'username' => 'test_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => 3,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $id = DB::table('user_channel_accounts')->insertGetId(array_merge([
            'user_id' => $userId,
            'channel_code' => 'TEST',
            'status' => UserChannelAccount::STATUS_ENABLE,
            'type' => UserChannelAccount::TYPE_DEPOSIT,
            'daily_total' => '100.00',
            'monthly_total' => '500.00',
            'withdraw_daily_total' => '50.00',
            'withdraw_monthly_total' => '200.00',
            'balance' => '1000.00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return UserChannelAccount::find($id);
    }

    // ---------------------------------------------------------------
    // updateTotal()
    // ---------------------------------------------------------------

    public function test_updateTotal_increases_daily_and_monthly_totals(): void
    {
        $account = $this->createUserChannelAccount([
            'daily_total' => '100.00',
            'monthly_total' => '500.00',
        ]);

        $this->util->updateTotal($account->id, '50.00');

        $account->refresh();
        $this->assertSame('150.00', $account->daily_total);
        $this->assertSame('550.00', $account->monthly_total);
    }

    public function test_updateTotal_with_withdraw_flag(): void
    {
        $account = $this->createUserChannelAccount([
            'withdraw_daily_total' => '50.00',
            'withdraw_monthly_total' => '200.00',
        ]);

        $this->util->updateTotal($account->id, '30.00', true);

        $account->refresh();
        $this->assertSame('80.00', $account->withdraw_daily_total);
        $this->assertSame('230.00', $account->withdraw_monthly_total);
    }

    public function test_updateTotal_returns_true_when_account_not_found(): void
    {
        $result = $this->util->updateTotal(999999, '50.00');

        $this->assertTrue($result);
    }

    // ---------------------------------------------------------------
    // updateTotalRollback()
    // ---------------------------------------------------------------

    public function test_updateTotalRollback_decreases_totals(): void
    {
        $account = $this->createUserChannelAccount([
            'daily_total' => '100.00',
            'monthly_total' => '500.00',
        ]);

        $this->util->updateTotalRollback($account->id, '30.00');

        $account->refresh();
        $this->assertSame('70.00', $account->daily_total);
        $this->assertSame('470.00', $account->monthly_total);
    }

    public function test_updateTotalRollback_does_not_go_below_zero(): void
    {
        $account = $this->createUserChannelAccount([
            'daily_total' => '10.00',
            'monthly_total' => '20.00',
        ]);

        $this->util->updateTotalRollback($account->id, '50.00');

        $account->refresh();
        $this->assertSame('0.00', $account->daily_total);
        $this->assertSame('0.00', $account->monthly_total);
    }

    public function test_updateTotalRollback_returns_true_when_not_found(): void
    {
        $result = $this->util->updateTotalRollback(999999, '50.00');

        $this->assertTrue($result);
    }
}
