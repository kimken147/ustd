<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Models\WhitelistedIp;
use App\Utils\PermissionUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class WhitelistedIpController extends Controller
{

    /**
     * @var PermissionUtil
     */
    private $permissionUtil;

    public function __construct(PermissionUtil $permissionUtil)
    {
        $this->permissionUtil = $permissionUtil;
    }

    public function batchUpdate(Request $request)
    {
        $this->validate($request, [
            'user_id' => 'required',
            'ipv4'    => 'required',
            'ipv4.*'  => 'ipv4',
            'type'    => ['required', 'int', Rule::in([WhitelistedIp::TYPE_LOGIN, WhitelistedIp::TYPE_API])],
            'status'  => 'required|int|in:0,1',
        ]);

        $userIds = collect($request->input('user_id'));

        /** @var User $user */
        $user = User::find($userIds->first());

        abort_if(!$user, Response::HTTP_BAD_REQUEST, '查无使用者');

        $this->abortIfPermissionDenied(auth()->user(), $user->mainUser()->role, $request->input('type'));

        $targetUserCount = User::whereIn('id', $userIds)->where(function (Builder $builder) use ($user) {
            $builder->where('role', $user->role)
                ->orWhereHas('parent', function (Builder $builder) use ($user) {
                    $builder->where('role', $user);
                });
        })->count();

        abort_if(
            $targetUserCount !== $userIds->count(),
            Response::HTTP_BAD_REQUEST,
            '帐号资料有误'
        );

        $ipv4Longs = collect($request->input('ipv4'))->map(function ($ipv4) {
            return ip2long($ipv4);
        });

        if ($request->boolean('status')) {
            // insert
            $now = now();
            $whitelistedIpValues = collect();

            foreach ($userIds as $userId) {
                foreach ($ipv4Longs as $ipv4Long) {
                    $whitelistedIpValues->add([
                        'type'       => $request->input('type'),
                        'user_id'    => $userId,
                        'ipv4'       => $ipv4Long,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            WhitelistedIp::insertIgnore($whitelistedIpValues->toArray());
        } else {
            // delete
            WhitelistedIp::whereIn('user_id', $userIds)
                ->whereIn('ipv4', $ipv4Longs)
                ->where('type', $request->input('type'))
                ->delete();
        }

        return response()->json(null, Response::HTTP_CREATED);
    }

    private function abortIfPermissionDenied(User $user, int $role, int $type)
    {
        switch ($role) {
            case User::ROLE_ADMIN:
                switch ($type) {
                    case WhitelistedIp::TYPE_LOGIN:
                        $this->permissionUtil->abortForbiddenIfPermissionDenied($user,
                            Permission::ADMIN_MANAGE_WHITELISTED_IP);
                        break;
                    default:
                        abort(Response::HTTP_BAD_REQUEST, '目前该角色尚未支援此白名单类型');
                }
                break;
            case User::ROLE_PROVIDER:
                switch ($type) {
                    case WhitelistedIp::TYPE_LOGIN:
                        $this->permissionUtil->abortForbiddenIfPermissionDenied($user,
                            Permission::ADMIN_MANAGE_PROVIDER_WHITELISTED_IP);
                        break;
                    default:
                        abort(Response::HTTP_BAD_REQUEST, '目前该角色尚未支援此白名单类型');
                }
            case User::ROLE_MERCHANT:
                switch ($type) {
                    case WhitelistedIp::TYPE_LOGIN:
                        $this->permissionUtil->abortForbiddenIfPermissionDenied($user,
                            Permission::ADMIN_MANAGE_MERCHANT_LOGIN_WHITELISTED_IP);
                        break;
                    case WhitelistedIp::TYPE_API:
                        $this->permissionUtil->abortForbiddenIfPermissionDenied($user,
                            Permission::ADMIN_MANAGE_MERCHANT_API_WHITELISTED_IP);
                        break;
                    default:
                        abort(Response::HTTP_INTERNAL_SERVER_ERROR);
                }
                break;
            default:
                abort(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function destroy(WhitelistedIp $whitelistedIp)
    {
        $this->abortIfPermissionDenied(auth()->user(), $whitelistedIp->user->mainUser()->role, $whitelistedIp->type);

        $whitelistedIp->delete();

        return response()->noContent();
    }

    public function store(Request $request, WhitelistedIpManager $whitelistedIpManager)
    {
        $this->validate($request, [
            'user_id' => 'required|int',
            'ipv4'    => 'required|ipv4',
            'type'    => ['required', 'int', Rule::in([WhitelistedIp::TYPE_LOGIN, WhitelistedIp::TYPE_API])],
        ]);

        // currently only support admins
        $user = User::where('id', $request->user_id)->first();

        abort_if(!$user, Response::HTTP_BAD_REQUEST, '查无帐号');

        $this->abortIfPermissionDenied(auth()->user(), $user->mainUser()->role, $request->input('type'));

        abort_if(
            $whitelistedIpManager->whitelistedIpExistsFor($user, $request->input('type'), $request->input('ipv4')),
            Response::HTTP_BAD_REQUEST,
            'IP 重复'
        );

        $whitelistedIp = WhitelistedIp::create([
            'user_id' => $request->input('user_id'),
            'ipv4'    => $request->input('ipv4'),
            'type'    => $request->input('type'),
        ]);

        return \App\Http\Resources\Admin\WhitelistedIp::make($whitelistedIp);
    }

    public function update(WhitelistedIp $whitelistedIp, Request $request, WhitelistedIpManager $whitelistedIpManager)
    {
        $this->validate($request, [
            'ipv4' => 'required|ipv4',
        ]);

        $this->abortIfPermissionDenied(auth()->user(), $whitelistedIp->user->mainUser()->role, $whitelistedIp->type);

        abort_if(
            $whitelistedIpManager->whitelistedIpExistsFor($whitelistedIp->user, $whitelistedIp->type,
                $request->input('ipv4')),
            Response::HTTP_BAD_REQUEST,
            'IP 重复'
        );

        $whitelistedIp->update([
            'ipv4' => $request->input('ipv4'),
        ]);

        return \App\Http\Resources\Admin\WhitelistedIp::make($whitelistedIp->unsetRelation('user'));
    }
}
