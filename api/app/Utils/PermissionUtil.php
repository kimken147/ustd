<?php


namespace App\Utils;


use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Response;

class PermissionUtil
{
    private $dontHavePermissionCache;

    public function dontHavePermission(User $user, $permissionId)
    {
        $cache = data_get($this->dontHavePermissionCache, "{$user->getKey()}.$permissionId");

        if ($cache) {
            return $cache;
        }

        $dontHavePermission = (
            $user->currentSubAccount
            && !$user->currentSubAccount->permissions()->where((new Permission())->getTable().'.id',
                $permissionId)->exists()
        );

        data_set($this->dontHavePermissionCache, "{$user->getKey()}.$permissionId", $dontHavePermission);

        return $dontHavePermission;
    }

    public function abortForbiddenIfPermissionDenied(User $user, $permissionId)
    {
        abort_if(
            $this->dontHavePermission($user, $permissionId),
            Response::HTTP_FORBIDDEN,
            __('permission.Denied')
        );
    }
}
