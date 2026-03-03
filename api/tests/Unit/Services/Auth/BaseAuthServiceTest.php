<?php

namespace Tests\Unit\Services\Auth;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\Auth\MerchantAuthService;
use App\Utils\LoginThrottle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Unit tests for BaseAuthService, using MerchantAuthService as the concrete implementation.
 */
class BaseAuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    private $loginThrottle;
    private MerchantAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginThrottle = Mockery::mock(LoginThrottle::class);
        $this->service = new MerchantAuthService($this->loginThrottle);
    }

    // ---------------------------------------------------------------
    //  Helpers
    // ---------------------------------------------------------------

    private function createUser(array $overrides = []): User
    {
        $defaults = [
            'name'              => 'TestMerchant',
            'username'          => 'MERCHANT' . mt_rand(100000, 999999),
            'password'          => Hash::make('password'),
            'role'              => User::ROLE_MERCHANT,
            'status'            => User::STATUS_ENABLE,
            'account_mode'      => User::ACCOUNT_MODE_GENERAL,
            'google2fa_enable'  => false,
            'google2fa_secret'  => strtoupper(bin2hex(random_bytes(8))),
            'secret_key'        => bin2hex(random_bytes(16)),
            'transaction_enable' => true,
            'deposit_enable'    => true,
            'withdraw_enable'   => true,
        ];

        $id = DB::table('users')->insertGetId(array_merge($defaults, $overrides));

        return User::find($id);
    }

    private function makeLoginRequest(array $data = []): LoginRequest
    {
        $defaults = [
            'username' => 'nonexistent',
            'password' => 'password',
        ];

        $merged = array_merge($defaults, $data);

        $request = LoginRequest::create('/api/login', 'POST', $merged);
        $request->setContainer(app());
        $request->setRedirector(app(\Illuminate\Routing\Redirector::class));
        $request->validateResolved();

        return $request;
    }

    private function allowThrottle(): void
    {
        $this->loginThrottle->shouldReceive('blocked')->andReturn(false);
    }

    // ---------------------------------------------------------------
    //  findUser (via login) — 2 tests
    // ---------------------------------------------------------------

    public function test_login_aborts_when_user_not_found(): void
    {
        $this->allowThrottle();

        $request = $this->makeLoginRequest(['username' => 'NONEXISTENT_USER']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(__('auth.failed'));

        $this->service->login($request);
    }

    public function test_login_aborts_when_user_disabled(): void
    {
        $user = $this->createUser(['status' => User::STATUS_DISABLE]);
        $this->allowThrottle();

        $request = $this->makeLoginRequest(['username' => $user->username]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(__('auth.account disabled'));

        $this->service->login($request);
    }

    // ---------------------------------------------------------------
    //  Throttle — 2 tests
    // ---------------------------------------------------------------

    public function test_login_aborts_when_throttle_blocked(): void
    {
        $this->loginThrottle->shouldReceive('blocked')->andReturn(true);

        $request = $this->makeLoginRequest();

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('请稍后再试');

        $this->service->login($request);
    }

    public function test_login_calls_handle_login_failure_on_wrong_password(): void
    {
        $user = $this->createUser();
        $this->allowThrottle();
        $this->loginThrottle->shouldReceive('count')->andReturn(false);
        $this->loginThrottle->shouldReceive('featureEnabled')->andReturn(false);

        $request = $this->makeLoginRequest([
            'username' => $user->username,
            'password' => 'wrong_password',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(__('auth.failed'));

        $this->service->login($request);
    }

    // ---------------------------------------------------------------
    //  changePassword — 4 tests
    // ---------------------------------------------------------------

    public function test_changePassword_succeeds_with_valid_old_password(): void
    {
        $user = $this->createUser([
            'password'         => Hash::make('oldpass'),
            'google2fa_enable' => false,
        ]);
        $this->actingAs($user, 'api');

        $request = Request::create('/api/change-password', 'POST', [
            'old_password' => 'oldpass',
            'new_password' => 'newpass123',
        ]);

        $this->service->changePassword($request);

        $user->refresh();
        $this->assertTrue(Hash::check('newpass123', $user->password));
        $this->assertFalse(Hash::check('oldpass', $user->password));
    }

    public function test_changePassword_aborts_with_wrong_old_password(): void
    {
        $user = $this->createUser([
            'password'         => Hash::make('oldpass'),
            'google2fa_enable' => false,
        ]);
        $this->actingAs($user, 'api');

        $request = Request::create('/api/change-password', 'POST', [
            'old_password' => 'wrongpass',
            'new_password' => 'newpass123',
        ]);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('旧密码错误');

        $this->service->changePassword($request);
    }

    public function test_changePassword_validates_required_fields(): void
    {
        $user = $this->createUser();
        $this->actingAs($user, 'api');

        $request = Request::create('/api/change-password', 'POST', []);

        $this->expectException(ValidationException::class);

        $this->service->changePassword($request);
    }

    public function test_changePassword_skips_2fa_when_not_enabled(): void
    {
        $user = $this->createUser([
            'password'         => Hash::make('myoldpass'),
            'google2fa_enable' => false,
        ]);
        $this->actingAs($user, 'api');

        $request = Request::create('/api/change-password', 'POST', [
            'old_password' => 'myoldpass',
            'new_password' => 'mynewpass',
        ]);

        // Should not throw — no OTP required
        $this->service->changePassword($request);

        $user->refresh();
        $this->assertTrue(Hash::check('mynewpass', $user->password));
    }

    // ---------------------------------------------------------------
    //  buildCredentials — 1 test
    // ---------------------------------------------------------------

    public function test_buildCredentials_includes_allowed_roles(): void
    {
        $request = $this->makeLoginRequest([
            'username' => 'someuser',
            'password' => 'somepass',
        ]);

        $method = new \ReflectionMethod($this->service, 'buildCredentials');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, $request);

        $this->assertArrayHasKey('username', $result);
        $this->assertArrayHasKey('password', $result);
        $this->assertArrayHasKey('role', $result);
        $this->assertEquals('someuser', $result['username']);
        $this->assertEquals('somepass', $result['password']);
        $this->assertEquals(
            [User::ROLE_MERCHANT, User::ROLE_MERCHANT_SUB_ACCOUNT],
            $result['role']
        );
    }

    // ---------------------------------------------------------------
    //  buildTokenResponse — 1 test
    // ---------------------------------------------------------------

    public function test_buildTokenResponse_returns_correct_structure(): void
    {
        $method = new \ReflectionMethod($this->service, 'buildTokenResponse');
        $method->setAccessible(true);

        $fakeToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.fake.token';
        $result = $method->invoke($this->service, $fakeToken);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertEquals($fakeToken, $result['access_token']);
        $this->assertEquals('bearer', $result['token_type']);
        $this->assertIsInt($result['expires_in']);
        $this->assertGreaterThan(0, $result['expires_in']);
    }

    // ---------------------------------------------------------------
    //  preLogin — 2 tests
    // ---------------------------------------------------------------

    public function test_preLogin_returns_google2fa_flag(): void
    {
        $user = $this->createUser([
            'password'         => Hash::make('correctpass'),
            'google2fa_enable' => true,
        ]);
        $this->allowThrottle();

        $request = $this->makeLoginRequest([
            'username' => $user->username,
            'password' => 'correctpass',
        ]);

        $result = $this->service->preLogin($request);

        $this->assertArrayHasKey('google2fa_enable', $result);
        $this->assertTrue($result['google2fa_enable']);
    }

    public function test_preLogin_aborts_when_user_not_found(): void
    {
        $this->allowThrottle();

        $request = $this->makeLoginRequest(['username' => 'NONEXISTENT_USER']);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage(__('auth.failed'));

        $this->service->preLogin($request);
    }
}
