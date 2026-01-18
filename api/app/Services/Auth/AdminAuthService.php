<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Utils\LoginThrottle;
use App\Utils\NotificationUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;

class AdminAuthService extends BaseAuthService
{
    public function __construct(
        LoginThrottle $loginThrottle,
        protected WhitelistedIpManager $whitelistedIpManager,
        protected NotificationUtil $notificationUtil,
    ) {
        parent::__construct($loginThrottle);
    }

    protected function getAllowedRoles(): array
    {
        return [User::ROLE_ADMIN, User::ROLE_SUB_ACCOUNT];
    }

    protected function validateAfterLogin(Request $request): void
    {
        abort_if(
            !$this->whitelistedIpManager->isAllowedToLoginFromRequest($request),
            Response::HTTP_BAD_REQUEST,
            __('IP 未加入白名单 :ip', ['ip' => $this->whitelistedIpManager->extractIpFromRequest($request)])
        );
    }

    protected function updateLoginRecord(?string $token): void
    {
        auth($this->getGuard())->user()->update([
            'last_login_at'   => now(),
            'last_login_ipv4' => Arr::last(request()->ips()),
            'token'           => md5($token),
        ]);
    }

    protected function afterLoginSuccess(Request $request): void
    {
        $this->notificationUtil->notifyAdminLogin(
            auth()->user()->realUser(),
            $this->whitelistedIpManager->extractIpFromRequest($request)
        );
    }

    protected function getPasswordChangeUser(): User
    {
        auth()->setUser(auth()->user()->realUser());
        return auth()->user();
    }
}
