<?php

namespace Tests\Unit\Utils;

use App\Models\User;
use App\Utils\AtomicLockUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AtomicLockUtilTest extends TestCase
{
    use DatabaseTransactions;

    private AtomicLockUtil $util;

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = new AtomicLockUtil();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): User
    {
        $id = DB::table('users')->insertGetId(array_merge([
            'name' => 'test_user',
            'username' => 'test_' . uniqid(),
            'password' => bcrypt('password'),
            'role' => User::ROLE_MERCHANT,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    // ---------------------------------------------------------------
    // keyForUserDeposit()
    // ---------------------------------------------------------------

    public function test_keyForUserDeposit_returns_correct_key(): void
    {
        $user = $this->createUser();

        $key = $this->util->keyForUserDeposit($user);

        $this->assertSame("deposit_of_{$user->id}", $key);
    }

    // ---------------------------------------------------------------
    // lock()
    // ---------------------------------------------------------------

    public function test_lock_executes_callback_and_returns_result(): void
    {
        $key = 'test-lock-' . uniqid();

        $result = $this->util->lock($key, function () {
            return 'callback-result';
        }, 10, 1);

        $this->assertSame('callback-result', $result);
    }

    public function test_lock_releases_lock_after_execution(): void
    {
        $key = 'test-lock-release-' . uniqid();

        $this->util->lock($key, function () {
            return true;
        }, 10, 1);

        // Lock should be released; acquiring again should succeed
        $result = $this->util->lock($key, function () {
            return 'second-call';
        }, 10, 1);

        $this->assertSame('second-call', $result);
    }
}
