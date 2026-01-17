<?php

namespace App\Http\Middleware;

use App\Utils\WhitelistedIpManager;
use Closure;
use Illuminate\Http\Response;

class CheckWhitelistedIp
{

    /**
     * @var WhitelistedIpManager
     */
    private $whitelistedIpManager;

    public function __construct(WhitelistedIpManager $whitelistedIpManager)
    {
        $this->whitelistedIpManager = $whitelistedIpManager;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->whitelistedIpManager->isAllowedToLoginFromRequest($request)) {
            return $next($request);
        }

        auth()->logout();

        abort(Response::HTTP_BAD_REQUEST,
            __('IP 未加入白名单 :ip', ['ip' => $this->whitelistedIpManager->extractIpFromRequest($request)]));
    }
}
