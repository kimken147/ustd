<?php

namespace App\Http\Middleware;

use App\Model\User;
use App\Model\FeatureToggle;
use App\Repository\FeatureToggleRepository;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckAccountToken
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
        $featureToggleRepository = app(FeatureToggleRepository::class);
        $token = '';
        if (\Str::startsWith($request->header('Authorization'), 'Bearer ')) {
            $token = \Str::substr($request->header('Authorization'), 7) ;
        }

        if ($request->has('token')) {
            $token = $request->token;
        }

        if (auth()->user()->realUser()->token !== md5($token) && !$featureToggleRepository->enabled(FeatureToggle::MULTI_DEVICES_LOGIN)) {
            auth()->logout();

            abort(Response::HTTP_UNAUTHORIZED, __('auth.sign out'));
        }

        return $next($request);
    }
}
