<?php

namespace Tests\Unit\Jobs;

use App\Jobs\MarkPaufenTransactionMatchingTimedOut;
use App\Models\Transaction;
use App\Models\User;
use App\Utils\WalletUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class MarkPaufenTransactionMatchingTimedOutTest extends TestCase
{
    use DatabaseTransactions;

    private $mockWallet;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockWallet = Mockery::mock(WalletUtil::class);
        $this->app->instance(WalletUtil::class, $this->mockWallet);

        Notification::fake();
        Redis::shouldReceive('set')->andReturn(false)->byDefault();
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
        $userId = $this->createUser()->getKey();
        $id = DB::table('transactions')->insertGetId(array_merge([
            'from_id' => $userId,
            'to_id' => $userId,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_MATCHING,
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

    public function test_handle_skips_when_status_is_not_matching(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_SUCCESS,
        ]);

        $job = new MarkPaufenTransactionMatchingTimedOut($transaction);
        $job->handle($this->mockWallet);

        $transaction->refresh();
        $this->assertEquals(
            Transaction::STATUS_SUCCESS,
            $transaction->status,
            'Status should remain SUCCESS when not in MATCHING state'
        );
    }

    public function test_handle_updates_status_to_matching_timed_out(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_MATCHING,
        ]);

        $job = new MarkPaufenTransactionMatchingTimedOut($transaction);
        $job->handle($this->mockWallet);

        $transaction->refresh();
        $this->assertEquals(
            Transaction::STATUS_MATCHING_TIMED_OUT,
            $transaction->status,
            'Status should be updated to MATCHING_TIMED_OUT'
        );
    }

    public function test_handle_does_not_update_when_already_changed(): void
    {
        $transaction = $this->createTransaction([
            'status' => Transaction::STATUS_MATCHING,
        ]);

        // Simulate a concurrent change: update status in DB directly before job runs
        // but the in-memory model still has STATUS_MATCHING
        DB::table('transactions')
            ->where('id', $transaction->getKey())
            ->update(['status' => Transaction::STATUS_PAYING]);

        // The job checks in-memory status first (still MATCHING), then does the WHERE
        // condition which won't match because DB status is now PAYING.
        // Re-fetch the transaction so the in-memory status check passes.
        // Actually, the job uses $this->transaction->status which was set at construction.
        // We need the in-memory object to still have STATUS_MATCHING.
        // The original $transaction model still has STATUS_MATCHING in memory.

        $job = new MarkPaufenTransactionMatchingTimedOut($transaction);
        $job->handle($this->mockWallet);

        // Verify the status was NOT changed to MATCHING_TIMED_OUT because the DB row
        // had already been changed to PAYING by the time the update query ran.
        $freshTransaction = Transaction::find($transaction->getKey());
        $this->assertEquals(
            Transaction::STATUS_PAYING,
            $freshTransaction->status,
            'Status should remain PAYING when concurrently changed before the update query'
        );
    }
}
