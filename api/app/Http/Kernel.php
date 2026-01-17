<?php

namespace App\Http;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\CheckAccountStatus;
use App\Http\Middleware\CheckAccountToken;
use App\Http\Middleware\CheckForMaintenanceMode;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\CheckWhitelistedIp;
use App\Http\Middleware\EncryptCookies;
use App\Http\Middleware\ExchangeModeEnabled;
use App\Http\Middleware\LogRequestResponse;
use App\Http\Middleware\ParseTextPlainJson; // 新增這一行
use App\Http\Middleware\RedirectIfAuthenticated;
use App\Http\Middleware\TrimStrings;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrustProxies;
use App\Http\Middleware\VerifyCsrfToken;
use Fruitcake\Cors\HandleCors;
use Illuminate\Auth\Middleware\AuthenticateWithBasicAuth;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\ValidatePostSize;
use Illuminate\Http\Middleware\SetCacheHeaders;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use jdavidbakr\CloudfrontProxies\CloudfrontProxies;

class Kernel extends HttpKernel
{

    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        HandleCors::class,
        TrustProxies::class,
        CheckForMaintenanceMode::class,
        ValidatePostSize::class,
        TrimStrings::class,
        // ConvertEmptyStringsToNull::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            ShareErrorsFromSession::class,
            // VerifyCsrfToken::class,
            SubstituteBindings::class,
        ],

        'api' => [
            SetLocale::class,
            LogRequestResponse::class,
            SubstituteBindings::class,
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'csrf'                  => VerifyCsrfToken::class,
        'auth'                  => Authenticate::class,
        'auth.basic'            => AuthenticateWithBasicAuth::class,
        'bindings'              => SubstituteBindings::class,
        'cache.headers'         => SetCacheHeaders::class,
        'can'                   => Authorize::class,
        'guest'                 => RedirectIfAuthenticated::class,
        'password.confirm'      => RequirePassword::class,
        'signed'                => ValidateSignature::class,
        'throttle'              => ThrottleRequests::class,
        'verified'              => EnsureEmailIsVerified::class,
        'role'                  => CheckRole::class,
        'permission'            => CheckPermission::class,
        'check.account.status'  => CheckAccountStatus::class,
        'check.account.token'   => CheckAccountToken::class,
        'check.whitelisted.ip'  => CheckWhitelistedIp::class,
        'exchange.mode.enabled' => ExchangeModeEnabled::class,
        'parse.textplain.json'  => ParseTextPlainJson::class, // 新增這一行
    ];
}
