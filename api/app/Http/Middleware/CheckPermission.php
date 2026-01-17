<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Utils\PermissionUtil;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CheckPermission
{

    /**
     * @var PermissionUtil
     */
    private $permissionUtil;

    public function __construct(PermissionUtil $permissionUtil)
    {
        $this->permissionUtil = $permissionUtil;
    }

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param  Closure  $next
     * @param $permissionId
     * @return mixed
     */
    public function handle($request, Closure $next, $permissionId)
    {
        /** @var User $user */
        $user = $request->user();

        $this->permissionUtil->abortForbiddenIfPermissionDenied($user, $permissionId);

        return $next($request);
    }
}
