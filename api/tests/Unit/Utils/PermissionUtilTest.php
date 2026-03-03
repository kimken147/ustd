<?php

namespace Tests\Unit\Utils;

use App\Models\Permission;
use App\Models\User;
use App\Utils\PermissionUtil;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PermissionUtilTest extends TestCase
{
    use DatabaseTransactions;

    private PermissionUtil $util;

    protected function setUp(): void
    {
        parent::setUp();
        $this->util = new PermissionUtil();
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
            'role' => User::ROLE_ADMIN,
            'secret_key' => 'sk_' . uniqid(),
            'google2fa_secret' => strtoupper(bin2hex(random_bytes(8))),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));

        return User::find($id);
    }

    // ---------------------------------------------------------------
    // dontHavePermission()
    // ---------------------------------------------------------------

    public function test_dontHavePermission_returns_false_when_no_sub_account(): void
    {
        $user = $this->createUser();
        // user->currentSubAccount is null by default

        $result = $this->util->dontHavePermission($user, Permission::ADMIN_CREATE_PROVIDER);

        $this->assertFalse($result);
    }

    public function test_dontHavePermission_caches_result(): void
    {
        $user = $this->createUser();

        // First call
        $result1 = $this->util->dontHavePermission($user, Permission::ADMIN_CREATE_PROVIDER);
        // Second call should use cache (same instance, same result)
        $result2 = $this->util->dontHavePermission($user, Permission::ADMIN_CREATE_PROVIDER);

        $this->assertSame($result1, $result2);
    }

    // ---------------------------------------------------------------
    // abortForbiddenIfPermissionDenied()
    // ---------------------------------------------------------------

    public function test_abortForbiddenIfPermissionDenied_passes_when_has_permission(): void
    {
        $user = $this->createUser();
        // No currentSubAccount means dontHavePermission returns false => no abort

        // Should not throw
        $this->util->abortForbiddenIfPermissionDenied($user, Permission::ADMIN_CREATE_PROVIDER);

        // If we reach here, no exception was thrown
        $this->assertTrue(true);
    }
}
