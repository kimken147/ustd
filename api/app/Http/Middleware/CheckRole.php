<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckRole
{

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param $role
     * @return mixed
     */
    public function handle($request, Closure $next, $role)
    {
        if (in_array($request->user()->role, [User::ROLE_SUB_ACCOUNT, User::ROLE_MERCHANT_SUB_ACCOUNT])) {
            /** @var User $parent */
            $parent = $request->user()->parent;

            $parent->currentSubAccount = $request->user();

            auth()->setUser($parent);
        }

        switch ($role) {
            case 'admin':
                abort_if(
                    $request->user()->role !== User::ROLE_ADMIN,
                    Response::HTTP_FORBIDDEN
                );
                break;
            case 'provider':
                abort_if(
                    $request->user()->role !== User::ROLE_PROVIDER,
                    Response::HTTP_FORBIDDEN
                );
                break;
            case 'merchant':
                abort_if(
                    $request->user()->role !== User::ROLE_MERCHANT,
                    Response::HTTP_FORBIDDEN
                );
                break;
            default:
                abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
