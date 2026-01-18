# AuthController 重構設計方案

## 概述

重構 4 個 AuthController（Admin、Merchant、Provider、Exchange），抽取共用邏輯至 Service 類別，消除重複程式碼並統一認證行為。

## 目標

1. **消除重複程式碼** - 把共用邏輯抽出來，減少維護成本
2. **統一行為** - 確保所有角色的認證流程一致
3. **保持現狀** - 各角色特有功能（IP 白名單、通知等）保留在各自的 Service

## 現況分析

### 現有 AuthController

| 檔案 | 行數 | 方法 |
|------|------|------|
| `Admin/AuthController.php` | 176 | login, preLogin, changePassword, me |
| `Merchant/AuthController.php` | 179 | login, preLogin, changePassword, me |
| `Provider/AuthController.php` | 354 | login, preLogin, changePassword, me, updateMe |
| `Exchange/AuthController.php` | 185 | login, preLogin, changePassword, me, updateMe |

### 共用邏輯相似度

| 方法 | 相似度 | 說明 |
|------|--------|------|
| `changePassword()` | ~90% | 幾乎完全相同 |
| `preLogin()` | ~80% | 主要差異在角色過濾 |
| `login()` | ~70% | 核心流程相同，各角色有特有邏輯 |
| `me()` | 各自不同 | 業務邏輯差異大，保留在 Controller |

### 各角色特有功能

| 功能 | Admin | Merchant | Provider | Exchange |
|------|-------|----------|----------|----------|
| IP 白名單檢查 | ✓ | | | |
| 登入通知 (Telegram) | ✓ | | | |
| 儲存 token hash | ✓ | | | |
| 記錄登入城市 | | | ✓ | |
| exchange_mode_enable 檢查 | | | | ✓ |

## 設計方案

### 架構

採用 **基底 + 角色專用 Service** 架構：

```
app/Services/Auth/
├── BaseAuthService.php          # 抽象基底類別，共用邏輯
├── AdminAuthService.php         # Admin 專用
├── MerchantAuthService.php      # Merchant 專用
├── ProviderAuthService.php      # Provider 專用
└── ExchangeAuthService.php      # Exchange 專用
```

### 類別關係

```
BaseAuthService (abstract)
    ├── AdminAuthService
    ├── MerchantAuthService
    ├── ProviderAuthService
    └── ExchangeAuthService
```

### BaseAuthService 設計

```php
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

    // ========== 抽象方法（子類別必須實作）==========

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

    // ========== 主要公開方法 ==========

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
        $this->validateUserBeforeAttempt($user);

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
        $this->validateUserBeforeAttempt($user);

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
     * 例如：Exchange 檢查 exchange_mode_enable
     */
    protected function validateUserBeforeAttempt(User $user): void
    {
        // 預設無額外驗證
    }

    /**
     * 登入成功後的額外驗證
     * 例如：Admin 檢查 IP 白名單
     */
    protected function validateAfterLogin(Request $request): void
    {
        // 預設無額外驗證
    }

    /**
     * 更新登入記錄
     * 子類別可覆寫以新增額外欄位
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
     * 例如：Admin 發送 Telegram 通知
     */
    protected function afterLoginSuccess(Request $request): void
    {
        // 預設無動作
    }

    /**
     * 取得修改密碼的目標使用者
     * Admin/Merchant 需要 realUser()，Provider/Exchange 直接用 auth()->user()
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
```

### AdminAuthService 設計

```php
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
        return auth()->user()->realUser();
    }
}
```

### MerchantAuthService 設計

```php
<?php

namespace App\Services\Auth;

use App\Models\User;

class MerchantAuthService extends BaseAuthService
{
    protected function getAllowedRoles(): array
    {
        return [User::ROLE_MERCHANT, User::ROLE_MERCHANT_SUB_ACCOUNT];
    }

    protected function getPasswordChangeUser(): User
    {
        return auth()->user()->realUser();
    }
}
```

### ProviderAuthService 設計

```php
<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Arr;
use Stevebauman\Location\Facades\Location;

class ProviderAuthService extends BaseAuthService
{
    protected function getAllowedRoles(): array
    {
        return [User::ROLE_PROVIDER];
    }

    protected function updateLoginRecord(?string $token): void
    {
        $ip = Arr::last(request()->ips());
        $city = str_replace('\'', ' ', optional(Location::get($ip))->cityName);

        auth($this->getGuard())->user()->update([
            'last_login_at'   => now(),
            'last_login_ipv4' => $ip,
            'last_login_city' => $city,
        ]);
    }
}
```

### ExchangeAuthService 設計

```php
<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Response;

class ExchangeAuthService extends BaseAuthService
{
    protected function getAllowedRoles(): array
    {
        return [User::ROLE_PROVIDER];
    }

    protected function validateUserBeforeAttempt(User $user): void
    {
        abort_if(
            !$user->exchange_mode_enable,
            Response::HTTP_BAD_REQUEST,
            '登入失败'
        );
    }
}
```

### 重構後的 Controller 範例

**Admin/AuthController.php：**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\User as UserResource;
use App\Services\Auth\AdminAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AdminAuthService $authService,
    ) {}

    public function login(LoginRequest $request)
    {
        return response()->json([
            'data' => $this->authService->login($request),
        ]);
    }

    public function preLogin(LoginRequest $request)
    {
        return response()->json([
            'data' => $this->authService->preLogin($request),
        ]);
    }

    public function changePassword(Request $request)
    {
        $this->authService->changePassword($request);
        return response()->noContent(Response::HTTP_OK);
    }

    public function me()
    {
        return UserResource::make(auth()->user()->realUser()->load('permissions'));
    }
}
```

## 預期效果

### 程式碼減少

| Controller | 重構前 | 重構後 | 減少 |
|------------|--------|--------|------|
| Admin/AuthController | 176 行 | ~35 行 | -80% |
| Merchant/AuthController | 179 行 | ~45 行 | -75% |
| Provider/AuthController | 354 行 | ~150 行 | -58% |
| Exchange/AuthController | 185 行 | ~50 行 | -73% |

> Provider 減少較少是因為 `me()` 方法有大量 stats 查詢邏輯保留在 Controller。

### 新增檔案

| 檔案 | 預估行數 |
|------|----------|
| `BaseAuthService.php` | ~200 行 |
| `AdminAuthService.php` | ~50 行 |
| `MerchantAuthService.php` | ~20 行 |
| `ProviderAuthService.php` | ~30 行 |
| `ExchangeAuthService.php` | ~25 行 |

### 優點

1. **減少重複** - 共用邏輯集中在 BaseAuthService
2. **易於維護** - 修改認證流程只需改一處
3. **易於擴展** - 新增角色只需建立新的 Service 子類別
4. **職責分離** - Controller 只負責 HTTP 層，Service 負責業務邏輯
5. **易於測試** - Service 可獨立進行單元測試

## 實作步驟

1. 建立 `app/Services/Auth/` 目錄
2. 實作 `BaseAuthService.php`
3. 實作 `AdminAuthService.php`
4. 重構 `Admin/AuthController.php` 並測試
5. 實作 `MerchantAuthService.php`
6. 重構 `Merchant/AuthController.php` 並測試
7. 實作 `ProviderAuthService.php`
8. 重構 `Provider/AuthController.php` 並測試
9. 實作 `ExchangeAuthService.php`
10. 重構 `Exchange/AuthController.php` 並測試
11. 執行完整測試套件確認無 regression

## 風險與注意事項

1. **行為差異** - 重構時需仔細比對各 Controller 的細微差異，確保行為一致
2. **測試覆蓋** - 建議在重構前確保有足夠的測試覆蓋
3. **漸進式重構** - 建議一次重構一個 Controller，確認無問題後再進行下一個
