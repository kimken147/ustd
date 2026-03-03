<?php

namespace Tests\Unit\Services\Auth;

use App\Models\User;
use App\Services\Auth\ProviderAuthService;
use App\Utils\LoginThrottle;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\TestCase;

class ProviderAuthServiceTest extends TestCase
{
    use DatabaseTransactions;

    private ProviderAuthService $service;
    private $loginThrottle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginThrottle = Mockery::mock(LoginThrottle::class);
        $this->service = new ProviderAuthService($this->loginThrottle);
    }

    // ---------------------------------------------------------------
    //  getAllowedRoles
    // ---------------------------------------------------------------

    public function test_getAllowedRoles_returns_provider(): void
    {
        $method = new \ReflectionMethod($this->service, 'getAllowedRoles');
        $method->setAccessible(true);

        $result = $method->invoke($this->service);

        $this->assertEquals([User::ROLE_PROVIDER], $result);
    }
}
