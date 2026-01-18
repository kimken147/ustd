<?php

namespace App\Services\Auth;

use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Utils\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FALaravel\Support\Authenticator;

abstract class BaseAuthService
{
    public function __construct(
        protected LoginThrottle $loginThrottle,
    ) {}

    /**
     * 定義允許登入的角色
     */
    abstract protected function getAllowedRoles(): array;

    /**
     * 取得用於 auth 的 guard 名稱
     */
    protected function getGuard(): string
    {
        return 'api';
    }

    /**
     * 登入
     */
    public function login(LoginRequest $request): array
    {
        $this->checkThrottleBlocked($request);
        $this->setAuthDriver();

        $credentials = $this->buildCredentials($request);
        $user = $this->findUser($request->input('username'));

        $this->validateUserStatus($user);
        $this->validateUserBeforeAttempt($user, $request);

        $token = $this->attemptLogin($credentials, $request);
        $this->validate2FA($request, $credentials['username']);
        $this->validateAfterLogin($request);

        DB::transaction(fn () => $this->updateLoginRecord($token));
        $this->afterLoginSuccess($request);

        $this->loginThrottle->clearCount($request);

        return $this->buildTokenResponse($token);
    }

    /**
     * 預登入（檢查是否需要 2FA）
     */
    public function preLogin(LoginRequest $request): array
    {
        $this->checkThrottleBlocked($request);
        $this->setAuthDriver();

        $credentials = $this->buildCredentials($request);
        $user = $this->findUser($request->input('username'));

        $this->validateUserStatus($user);
        $this->validateUserBeforeAttempt($user, $request);

        if (auth($this->getGuard())->attempt($credentials)) {
            abort_if(
                auth()->user()->status === User::STATUS_DISABLE,
                Response::HTTP_BAD_REQUEST,
                __('auth.account disabled')
            );

            return [
                'google2fa_enable' => auth($this->getGuard())->user()->google2fa_enable,
            ];
        }

        $this->handleLoginFailure($request, $credentials['username']);
    }

    /**
     * 修改密碼
     */
    public function changePassword(Request $request): void
    {
        $this->validateChangePasswordRequest($request);

        $user = $this->getPasswordChangeUser();

        abort_if(
            !Hash::check($request->old_password, $user->password),
            Response::HTTP_BAD_REQUEST,
            '旧密码错误'
        );

        if ($user->google2fa_enable) {
            $this->validate2FAForPasswordChange($request);
        }

        $user->update([
            'password' => Hash::make($request->new_password),
        ]);
    }

    // ========== Hook 方法（子類別可覆寫）==========

    /**
     * 在嘗試登入前的額外驗證
     */
    protected function validateUserBeforeAttempt(User $user, Request $request): void
    {
        // 預設無額外驗證
    }

    /**
     * 登入成功後的額外驗證
     */
    protected function validateAfterLogin(Request $request): void
    {
        // 預設無額外驗證
    }

    /**
     * 更新登入記錄
     */
    protected function updateLoginRecord(?string $token): void
    {
        auth($this->getGuard())->user()->update([
            'last_login_at'   => now(),
            'last_login_ipv4' => Arr::last(request()->ips()),
        ]);
    }

    /**
     * 登入成功後的回呼
     */
    protected function afterLoginSuccess(Request $request): void
    {
        // 預設無動作
    }

    /**
     * 取得修改密碼的目標使用者
     */
    protected function getPasswordChangeUser(): User
    {
        return auth()->user();
    }

    // ========== 內部方法 ==========

    protected function checkThrottleBlocked(Request $request): void
    {
        abort_if(
            $this->loginThrottle->blocked($request),
            Response::HTTP_BAD_REQUEST,
            '请稍后再试'
        );
    }

    protected function setAuthDriver(): void
    {
        auth()->setDefaultDriver($this->getGuard());
    }

    protected function buildCredentials(LoginRequest $request): array
    {
        return $request->only('username', 'password') + ['role' => $this->getAllowedRoles()];
    }

    protected function findUser(string $username): User
    {
        $user = User::where('username', $username)->first();
        abort_if(!$user, Response::HTTP_BAD_REQUEST, __('auth.failed'));
        return $user;
    }

    protected function validateUserStatus(User $user): void
    {
        abort_if(
            $user->status === User::STATUS_DISABLE,
            Response::HTTP_BAD_REQUEST,
            __('auth.account disabled')
        );
    }

    protected function attemptLogin(array $credentials, Request $request): string
    {
        $token = auth($this->getGuard())->attempt($credentials);

        if (!$token) {
            $this->handleLoginFailure($request, $credentials['username']);
        }

        abort_if(
            auth()->user()->status === User::STATUS_DISABLE,
            Response::HTTP_BAD_REQUEST,
            __('auth.account disabled')
        );

        return $token;
    }

    protected function validate2FA(Request $request, string $username): void
    {
        if (!auth($this->getGuard())->user()->google2fa_enable) {
            return;
        }

        $request->validate([
            config('google2fa.otp_input') => 'required|string',
        ]);

        $this->setAuthDriver();

        /** @var Authenticator $authenticator */
        $authenticator = app(Authenticator::class)->bootStateless($request);

        if (!$authenticator->isAuthenticated()) {
            abort_if(
                $this->loginThrottle->count($request, $username),
                Response::HTTP_BAD_REQUEST,
                '请稍后再试'
            );

            $errorMessage = __('google2fa.Invalid OTP');

            if ($this->loginThrottle->featureEnabled()) {
                $errorMessage = '谷歌验证码错误，失败次数过多将会被系统封锁，请务必再次确认！';
            }

            abort(Response::HTTP_BAD_REQUEST, $errorMessage);
        }
    }

    protected function handleLoginFailure(Request $request, string $username): never
    {
        abort_if(
            $this->loginThrottle->count($request, $username),
            Response::HTTP_BAD_REQUEST,
            '请稍后再试'
        );

        $errorMessage = __('auth.failed');

        if ($this->loginThrottle->featureEnabled()) {
            $errorMessage = '帐号或密码错误，登入失败次数过多将会被系统封锁，请再次确认帐号密码！';
        }

        abort(Response::HTTP_BAD_REQUEST, $errorMessage);
    }

    protected function buildTokenResponse(string $token): array
    {
        return [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => auth($this->getGuard())->factory()->getTTL() * 60,
        ];
    }

    protected function validateChangePasswordRequest(Request $request): void
    {
        $request->validate([
            'old_password' => 'required',
            'new_password' => 'required',
        ]);
    }

    protected function validate2FAForPasswordChange(Request $request): void
    {
        $request->validate([
            config('google2fa.otp_input') => 'required|string',
        ]);

        /** @var Authenticator $authenticator */
        $authenticator = app(Authenticator::class)->bootStateless($request);

        abort_if(
            !$authenticator->isAuthenticated(),
            Response::HTTP_BAD_REQUEST,
            __('google2fa.Invalid OTP')
        );
    }
}
