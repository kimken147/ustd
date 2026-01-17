<?php

namespace App\Http\Middleware;

use App\Model\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExchangeModeEnabled
{

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (!auth()->user()->realUser()->exchange_mode_enable) {
            auth()->logout();

            abort(Response::HTTP_BAD_REQUEST, '登入失败');
        }

        return $next($request);
    }
}
