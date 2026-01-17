<?php

namespace App\Utils;


use App\Model\User;
use App\Model\WhitelistedIp;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class WhitelistedIpManager
{

    /**
     * @param  Request  $request
     * @param  null  $guard
     * @return bool
     */
    public function isAllowedToLoginFromRequest(Request $request, $guard = null)
    {
        $clientIp = $this->extractIpFromRequest($request);
        /** @var User $user */
        $user = optional($request->user($guard))->realUser();

        if (!$user) {
            return false;
        }

        if ($this->isInvalidIpv4($clientIp)) {
            return false;
        }

        // 沒有設定 IP 白名單時預設不擋 IP
        if ($this->loginWhitelistedIpIsEmptyFor($user) || $this->loginWhitelistedIpExistsFor($user, $clientIp)) {
            return true;
        }

        return false;
    }

    public function extractIpFromRequest(Request $request)
    {
        return Arr::last($request->ips());
    }

    public function isInvalidIpv4(string $ipv4)
    {
        return !filter_var($ipv4, FILTER_VALIDATE_IP);
    }

    public function loginWhitelistedIpIsEmptyFor(User $user)
    {
        return !WhitelistedIp::ofUser($user)->ofType(WhitelistedIp::TYPE_LOGIN)->exists();
    }

    public function whitelistedIpIsEmptyFor(User $user, int $type)
    {
        return !WhitelistedIp::ofUser($user)->ofType($type)->exists();
    }

    public function loginWhitelistedIpExistsFor(User $user, string $ipv4)
    {
        return WhitelistedIp::ofUser($user)->ofType(WhitelistedIp::TYPE_LOGIN)->ofIpv4($ipv4)->exists();
    }

    public function isNotAllowedToUseThirdPartyApi(User $user, Request $request)
    {
        $clientIp = $this->extractIpFromRequest($request);

        /** @var User $user */
        $user = $user->mainUser();

        if ($this->isInvalidIpv4($clientIp)) {
            return true;
        }

        // 沒有設定 IP 白名單時預設不擋 IP
        if ($this->whitelistedIpIsEmptyFor($user, WhitelistedIp::TYPE_API) || $this->whitelistedIpExistsFor($user, WhitelistedIp::TYPE_API, $clientIp)) {
            return false;
        }

        return true;
    }

    public function whitelistedIpExistsFor(User $user, int $type, string $ipv4)
    {
        return WhitelistedIp::ofUser($user)->ofType($type)->ofIpv4($ipv4)->exists();
    }

    public function userHasMoreThanOneLoginWhitelistedIp(User $user)
    {
        return WhitelistedIp::ofUser($user)->ofType(WhitelistedIp::TYPE_LOGIN)->count() > 1;
    }
}
