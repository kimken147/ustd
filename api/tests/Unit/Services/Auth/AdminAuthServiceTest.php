<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\AdminAuthService;
use App\Utils\LoginThrottle;
use App\Utils\NotificationUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminAuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AdminAuthService $service;
    private $loginThrottle;
    private $whitelistedIpManager;
    private $notificationUtil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginThrottle = Mockery::mock(LoginThrottle::class);
        $this->whitelistedIpManager = Mockery::mock(WhitelistedIpManager::class);
        $this->notificationUtil = Mockery::mock(NotificationUtil::class);

        $this->service = new AdminAuthService(
            $this->loginThrottle,
            $this->whitelistedIpManager,
            $this->notificationUtil
        );
    }

    // ---------------------------------------------------------------
    //  getAllowedRoles
    // ---------------------------------------------------------------

    public function test_getAllowedRoles_returns_admin_and_sub_account(): void
    {
        $method = new \ReflectionMethod($this->service, 'getAllowedRoles');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertEquals([User::ROLE_ADMIN, User::ROLE_SUB_ACCOUNT], $result);
    }

    // ---------------------------------------------------------------
    //  validateAfterLogin
    // ---------------------------------------------------------------

    public function test_validateAfterLogin_passes_when_ip_allowed(): void
    {
        $request = Request::create('/api/login', 'POST');

        $this->whitelistedIpManager
            ->shouldReceive('isAllowedToLoginFromRequest')
            ->with($request)
            ->once()
            ->andReturn(true);

        // abort_if evaluates its message argument eagerly, so extractIpFromRequest is always called
        $this->whitelistedIpManager
            ->shouldReceive('extractIpFromRequest')
            ->with($request)
            ->andReturn('127.0.0.1');

        $method = new \ReflectionMethod($this->service, 'validateAfterLogin');
        $method->setAccessible(true);

        // Should not throw
        $method->invoke($this->service, $request);

        $this->assertTrue(true);
    }

    public function test_validateAfterLogin_throws_when_ip_not_allowed(): void
    {
        $request = Request::create('/api/login', 'POST');

        $this->whitelistedIpManager
            ->shouldReceive('isAllowedToLoginFromRequest')
            ->with($request)
            ->once()
            ->andReturn(false);

        $this->whitelistedIpManager
            ->shouldReceive('extractIpFromRequest')
            ->with($request)
            ->once()
            ->andReturn('192.168.1.100');

        $method = new \ReflectionMethod($this->service, 'validateAfterLogin');
        $method->setAccessible(true);

        $this->expectException(HttpException::class);

        $method->invoke($this->service, $request);
    }
}
