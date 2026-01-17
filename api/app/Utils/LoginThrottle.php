<?php


namespace App\Utils;


use App\Model\FeatureToggle;
use App\Model\User;
use App\Repository\FeatureToggleRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class LoginThrottle
{

    /**
     * @var FeatureToggleRepository
     */
    private $featureToggleRepository;
    /**
     * @var NotificationUtil
     */
    private $notificationUtil;
    /**
     * @var WhitelistedIpManager
     */
    private $whitelistedIpManager;

    public function __construct(
        FeatureToggleRepository $featureToggleRepository,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager
    ) {
        $this->featureToggleRepository = $featureToggleRepository;
        $this->notificationUtil = $notificationUtil;
        $this->whitelistedIpManager = $whitelistedIpManager;
    }

    public function count(Request $request, string $tryingUsername)
    {
        if (!$this->featureEnabled()) {
            return false;
        }

        $limitCount = max($this->featureToggleRepository->valueOf(FeatureToggle::LOGIN_THROTTLE,
            3), 1);
        $clientIp = $this->whitelistedIpManager->extractIpFromRequest($request);
        $key = $this->getCacheKey($clientIp);
        $blockKey = $this->getBlockKey($clientIp);
        $countKey = $this->getCountKey($clientIp);

        Redis::funnel($key)->limit(1)->then(function () use (
            $blockKey,
            $countKey,
            $limitCount,
            $clientIp,
            $request,
            $tryingUsername
        ) {
            if (Cache::get($blockKey)) {
                return true;
            }

            $currentCount = 1;
            $countKeyAdded = Cache::add($countKey, $currentCount, now()->addMinutes(5));

            if (!$countKeyAdded) {
                $currentCount = Cache::increment($countKey);
            }

            if ($currentCount >= $limitCount) {
                Cache::add($blockKey, true, now()->addMinutes(1));
                Cache::forget($countKey);

                $this->notificationUtil->notifyLoginThrottle($tryingUsername, $clientIp);

                return true;
            }

            return false;
        }, function () {
            return true;
        });
    }

    private function getCacheKey(string $ipv4)
    {
        return 'login-throttle-ip-'.$ipv4;
    }

    private function getBlockKey(string $ipv4)
    {
        return 'block-'.$this->getCacheKey($ipv4);
    }

    private function getCountKey(string $ipv4)
    {
        return 'count-'.$this->getCacheKey($ipv4);
    }

    public function featureEnabled()
    {
        return $this->featureToggleRepository->enabled(FeatureToggle::LOGIN_THROTTLE);
    }

    public function unlock(Request $request)
    {
        return Cache::forget($this->getBlockKey($this->whitelistedIpManager->extractIpFromRequest($request)));
    }

    public function clearCount(Request $request)
    {
        return Cache::forget($this->getCountKey($this->whitelistedIpManager->extractIpFromRequest($request)));
    }

    public function blocked(Request $request)
    {
        if (!$this->featureEnabled()) {
            return false;
        }

        return Cache::has($this->getBlockKey($this->whitelistedIpManager->extractIpFromRequest($request)));
    }
}
