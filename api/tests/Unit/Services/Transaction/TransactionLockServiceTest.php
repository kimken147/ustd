<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\Transaction;
use App\Models\User;
use App\Services\Transaction\TransactionLockService;
use App\Utils\TransactionLockFailed;
use App\Utils\TransactionUnlockFailed;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use Tests\TestCase;

class TransactionLockServiceTest extends TestCase
{
    use DatabaseTransactions;

    private TransactionLockService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TransactionLockService();
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
            'name' => 'admin_user',
            'username' => 'admin_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_ADMIN,
            'god' => false,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => 'g2fa_' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    private function createTransaction(array $overrides = []): Transaction
    {
        $id = DB::table('transactions')->insertGetId(array_merge([
            'from_id' => 0,
            'to_id' => 0,
            'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
            'status' => Transaction::STATUS_PAYING,
            'notify_status' => 0,
            'from_channel_account' => json_encode([]),
            'to_channel_account' => json_encode([]),
            'amount' => '100.00',
            'floating_amount' => '100.00',
            'actual_amount' => '0.00',
            'locked_at' => null,
            'locked_by_id' => null,
            'order_number' => 'TEST' . uniqid(),
            'system_order_number' => 'SYS' . uniqid(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return Transaction::find($id);
    }

    // ===============================================================
    //  lock()
    // ===============================================================

    public function test_lock_happy_path_sets_locked_at_and_locked_by(): void
    {
        $adminUser = $this->createUser(['role' => User::ROLE_ADMIN]);
        $transaction = $this->createTransaction();

        $result = $this->service->lock($transaction, $adminUser);

        $this->assertNotNull($result->locked_at);
        $this->assertEquals($adminUser->id, $result->locked_by_id);
        $this->assertTrue($result->locked);
    }

    public function test_lock_throws_invalid_argument_for_non_admin_without_force(): void
    {
        $merchantUser = $this->createUser(['role' => User::ROLE_MERCHANT]);
        $transaction = $this->createTransaction();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Non admin user');

        $this->service->lock($transaction, $merchantUser);
    }

    public function test_lock_allows_non_admin_with_force(): void
    {
        $merchantUser = $this->createUser(['role' => User::ROLE_MERCHANT]);
        $transaction = $this->createTransaction();

        $result = $this->service->lock($transaction, $merchantUser, true);

        $this->assertNotNull($result->locked_at);
        $this->assertEquals($merchantUser->id, $result->locked_by_id);
        $this->assertTrue($result->locked);
    }

    public function test_lock_throws_lock_failed_when_already_locked(): void
    {
        $adminUser = $this->createUser(['role' => User::ROLE_ADMIN]);
        $otherAdmin = $this->createUser(['role' => User::ROLE_ADMIN]);
        $transaction = $this->createTransaction([
            'locked_at' => now(),
            'locked_by_id' => $otherAdmin->id,
        ]);

        $this->expectException(TransactionLockFailed::class);

        $this->service->lock($transaction, $adminUser);
    }

    public function test_lock_returns_refreshed_transaction(): void
    {
        $adminUser = $this->createUser(['role' => User::ROLE_ADMIN]);
        $transaction = $this->createTransaction();

        // Confirm it starts unlocked
        $this->assertNull($transaction->locked_at);

        $result = $this->service->lock($transaction, $adminUser);

        // The returned transaction should have the updated locked_at
        $this->assertNotNull($result->locked_at);
        $this->assertEquals($adminUser->id, $result->locked_by_id);

        // Verify the DB was updated by re-fetching
        $fresh = Transaction::find($transaction->id);
        $this->assertNotNull($fresh->locked_at);
        $this->assertEquals($adminUser->id, $fresh->locked_by_id);
    }

    // ===============================================================
    //  unlock()
    // ===============================================================

    public function test_unlock_happy_path_clears_locked_at_and_locked_by(): void
    {
        $adminUser = $this->createUser(['role' => User::ROLE_ADMIN]);
        $transaction = $this->createTransaction([
            'locked_at' => now(),
            'locked_by_id' => $adminUser->id,
        ]);

        $this->actingAs($adminUser);

        $result = $this->service->unlock($transaction, $adminUser);

        $this->assertNull($result->locked_at);
        $this->assertNull($result->locked_by_id);
        $this->assertFalse($result->locked);
    }

    public function test_unlock_throws_invalid_argument_for_non_admin_without_force(): void
    {
        $merchantUser = $this->createUser(['role' => User::ROLE_MERCHANT]);
        $transaction = $this->createTransaction([
            'locked_at' => now(),
            'locked_by_id' => $merchantUser->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Non admin user');

        $this->service->unlock($transaction, $merchantUser);
    }

    public function test_unlock_god_user_can_unlock_anyone_lock(): void
    {
        $lockerAdmin = $this->createUser(['role' => User::ROLE_ADMIN, 'god' => false]);
        $godAdmin = $this->createUser(['role' => User::ROLE_ADMIN, 'god' => true]);

        $transaction = $this->createTransaction([
            'locked_at' => now(),
            'locked_by_id' => $lockerAdmin->id,
        ]);

        $this->actingAs($godAdmin);

        $result = $this->service->unlock($transaction, $godAdmin);

        $this->assertNull($result->locked_at);
        $this->assertNull($result->locked_by_id);
        $this->assertFalse($result->locked);
    }

    public function test_unlock_throws_unlock_failed_when_already_unlocked(): void
    {
        $adminUser = $this->createUser(['role' => User::ROLE_ADMIN]);
        $transaction = $this->createTransaction([
            'locked_at' => null,
            'locked_by_id' => null,
        ]);

        $this->actingAs($adminUser);

        $this->expectException(TransactionUnlockFailed::class);

        $this->service->unlock($transaction, $adminUser);
    }

    public function test_unlock_non_god_cannot_unlock_other_users_lock(): void
    {
        $adminA = $this->createUser(['role' => User::ROLE_ADMIN, 'god' => false]);
        $adminB = $this->createUser(['role' => User::ROLE_ADMIN, 'god' => false]);

        $transaction = $this->createTransaction([
            'locked_at' => now(),
            'locked_by_id' => $adminA->id,
        ]);

        $this->actingAs($adminB);

        // adminB is not god, not the lock owner, and adminA is not a sub-account of adminB
        // The WHERE clause won't match, leading to TransactionUnlockFailed or RuntimeException
        $this->expectException(\Exception::class);

        $this->service->unlock($transaction, $adminB);
    }

    // ===============================================================
    //  supportLockingLogics()
    // ===============================================================

    public function test_supportLockingLogics_locks_when_request_locked_true(): void
    {
        $adminUser = $this->createUser(['role' => User::ROLE_ADMIN]);
        $transaction = $this->createTransaction();

        $this->actingAs($adminUser);

        $request = Request::create('/test', 'POST', ['locked' => true]);
        $request->setUserResolver(fn () => $adminUser);

        $this->service->supportLockingLogics($transaction, $request);

        $transaction->refresh();
        $this->assertTrue($transaction->locked);
        $this->assertEquals($adminUser->id, $transaction->locked_by_id);
    }

    public function test_supportLockingLogics_unlocks_when_request_locked_false(): void
    {
        $adminUser = $this->createUser(['role' => User::ROLE_ADMIN]);
        $transaction = $this->createTransaction([
            'locked_at' => now(),
            'locked_by_id' => $adminUser->id,
        ]);

        $this->actingAs($adminUser);

        $request = Request::create('/test', 'POST', ['locked' => false]);
        $request->setUserResolver(fn () => $adminUser);

        $this->service->supportLockingLogics($transaction, $request);

        $transaction->refresh();
        $this->assertFalse($transaction->locked);
        $this->assertNull($transaction->locked_at);
        $this->assertNull($transaction->locked_by_id);
    }

    public function test_supportLockingLogics_calls_beforeLock_closure(): void
    {
        $adminUser = $this->createUser(['role' => User::ROLE_ADMIN]);
        $transaction = $this->createTransaction();

        $this->actingAs($adminUser);

        $request = Request::create('/test', 'POST', ['locked' => true]);
        $request->setUserResolver(fn () => $adminUser);

        $closureCalled = false;
        $beforeLock = function () use (&$closureCalled) {
            $closureCalled = true;
        };

        $this->service->supportLockingLogics($transaction, $request, $beforeLock);

        $this->assertTrue($closureCalled);
        $transaction->refresh();
        $this->assertTrue($transaction->locked);
    }
}
