<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckAccountStatus
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
        if (auth()->user()->realUser()->status === User::STATUS_DISABLE) {
            auth()->logout();

            abort(Response::HTTP_BAD_REQUEST, __('auth.account disabled'));
        }

        return $next($request);
    }
}
