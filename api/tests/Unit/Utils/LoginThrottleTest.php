<?php

namespace Tests\Unit\Utils;

use App\Models\FeatureToggle;
use App\Repository\FeatureToggleRepository;
use App\Utils\LoginThrottle;
use App\Utils\NotificationUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class LoginThrottleTest extends TestCase
{
    use DatabaseTransactions;

    /** @var FeatureToggleRepository|Mockery\MockInterface */
    private $featureToggleRepository;

    /** @var NotificationUtil|Mockery\MockInterface */
    private $notificationUtil;

    /** @var WhitelistedIpManager|Mockery\MockInterface */
    private $whitelistedIpManager;

    private LoginThrottle $loginThrottle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->featureToggleRepository = Mockery::mock(FeatureToggleRepository::class);
        $this->notificationUtil = Mockery::mock(NotificationUtil::class);
        $this->whitelistedIpManager = Mockery::mock(WhitelistedIpManager::class);

        $this->loginThrottle = new LoginThrottle(
            $this->featureToggleRepository,
            $this->notificationUtil,
            $this->whitelistedIpManager
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createRequest(string $ip = '10.0.0.1'): Request
    {
        return Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => $ip]);
    }

    // ---------------------------------------------------------------
    // featureEnabled()
    // ---------------------------------------------------------------

    public function test_featureEnabled_returns_true_when_enabled(): void
    {
        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::LOGIN_THROTTLE)
            ->once()
            ->andReturn(true);

        $this->assertTrue($this->loginThrottle->featureEnabled());
    }

    public function test_featureEnabled_returns_false_when_disabled(): void
    {
        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::LOGIN_THROTTLE)
            ->once()
            ->andReturn(false);

        $this->assertFalse($this->loginThrottle->featureEnabled());
    }

    // ---------------------------------------------------------------
    // blocked()
    // ---------------------------------------------------------------

    public function test_blocked_returns_false_when_feature_disabled(): void
    {
        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::LOGIN_THROTTLE)
            ->once()
            ->andReturn(false);

        $request = $this->createRequest();

        $this->assertFalse($this->loginThrottle->blocked($request));
    }

    public function test_blocked_returns_true_when_cache_has_block_key(): void
    {
        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::LOGIN_THROTTLE)
            ->once()
            ->andReturn(true);

        $this->whitelistedIpManager->shouldReceive('extractIpFromRequest')
            ->once()
            ->andReturn('10.0.0.1');

        Cache::put('block-login-throttle-ip-10.0.0.1', true, now()->addMinutes(1));

        $request = $this->createRequest();

        $this->assertTrue($this->loginThrottle->blocked($request));
    }

    public function test_blocked_returns_false_when_no_block_key(): void
    {
        $this->featureToggleRepository->shouldReceive('enabled')
            ->with(FeatureToggle::LOGIN_THROTTLE)
            ->once()
            ->andReturn(true);

        $this->whitelistedIpManager->shouldReceive('extractIpFromRequest')
            ->once()
            ->andReturn('10.0.0.1');

        Cache::forget('block-login-throttle-ip-10.0.0.1');

        $request = $this->createRequest();

        $this->assertFalse($this->loginThrottle->blocked($request));
    }

    // ---------------------------------------------------------------
    // unlock()
    // ---------------------------------------------------------------

    public function test_unlock_forgets_block_key(): void
    {
        $this->whitelistedIpManager->shouldReceive('extractIpFromRequest')
            ->once()
            ->andReturn('10.0.0.1');

        Cache::put('block-login-throttle-ip-10.0.0.1', true, now()->addMinutes(5));

        $request = $this->createRequest();

        $this->loginThrottle->unlock($request);

        $this->assertFalse(Cache::has('block-login-throttle-ip-10.0.0.1'));
    }

    // ---------------------------------------------------------------
    // clearCount()
    // ---------------------------------------------------------------

    public function test_clearCount_forgets_count_key(): void
    {
        $this->whitelistedIpManager->shouldReceive('extractIpFromRequest')
            ->once()
            ->andReturn('10.0.0.1');

        Cache::put('count-login-throttle-ip-10.0.0.1', 3, now()->addMinutes(5));

        $request = $this->createRequest();

        $this->loginThrottle->clearCount($request);

        $this->assertFalse(Cache::has('count-login-throttle-ip-10.0.0.1'));
    }
}
