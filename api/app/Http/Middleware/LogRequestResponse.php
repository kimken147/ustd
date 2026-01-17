<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogRequestResponse
{
    /**
     * Routes to be excluded from logging.
     *
     * @var array
     */
    protected $excludedGetRoutes = [
        'api/v1/admin/actions/*', // 新增的帶通配符的路由
        "api/v1/admin/*",
        "api/v1/merchant/*",
        "api/v1/transactions/*",
        "api/v1/cashier/*"
    ];

    protected $excludedPostRoutes = [
        "api/v1/third-party/profile-queries",
        "api/v1/transactions/*/note"
    ];

    protected $excludedPutRoutes = [
        "api/v1/transactions/*"
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if (!$this->shouldExclude($request)) {
            $limitedRequest = Str::limit((string) $request, 1500);
            $limitedResponse = Str::limit((string) $response, 1500);

            $requestParameters = collect($request->all());

            // Uncomment the following block if you want to mask sensitive data
            /*
            $requestParameters = $requestParameters->map(function ($requestParameter, $attribute) {
                if (Str::contains($attribute, ['password', 'secret'])) {
                    return str_repeat('*', Str::length($requestParameter));
                }
                return $requestParameter;
            });
            */

            Log::debug(
                $limitedRequest
                    . PHP_EOL . '=========' . PHP_EOL
                    . $limitedResponse,
                $requestParameters->toArray()
            );
        }

        return $response;
    }

    /**
     * Determine if the request should be excluded from logging.
     *
     * @param  Request  $request
     * @return bool
     */
    protected function shouldExclude(Request $request): bool
    {
        $path = $request->path(); // 獲取當前請求的路徑

        if ($request->isMethod('GET')) {
            $path = $request->path();

            foreach ($this->excludedGetRoutes as $route) {
                if (Str::is($route, $path)) {
                    return true;
                }
            }
        }

        if ($request->isMethod("POST")) {
            $path = $request->path();

            foreach ($this->excludedPostRoutes as $route) {
                if (Str::is($route, $path)) {
                    return true;
                }
            }
        }

        if ($request->isMethod("PUT")) {
            foreach ($this->excludedPutRoutes as $route) {
                if (Str::is($route, $path)) {
                    return true;
                }
            }
        }

        return false;
    }
}
