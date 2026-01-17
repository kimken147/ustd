<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\User;
use App\Models\BannedIp;
use App\Models\BannedRealname;
use App\Utils\PermissionUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use App\Http\Resources\Admin\Banned;
use App\Http\Resources\Admin\BannedCollection;
use Throwable;

class BannedController extends Controller
{

    /**
     * @var PermissionUtil
     */
    private $permissionUtil;

    public function __construct(PermissionUtil $permissionUtil)
    {
        $this->permissionUtil = $permissionUtil;
    }

    public function getBanIp(Request $request)
    {
        $this->validate($request, [
            'ipv4'    => 'nullable|ipv4',
            'type'    => ['nullable', 'int', Rule::in([BannedIp::TYPE_TRANSACTION])],
        ]);

        $user = auth()->user();

        $results = BannedIp::orderByDesc('created_at');

        $results->when($request->ipv4, function ($builder, $ip) {
            $builder->where('ipv4', ip2long($ip));
        });

        return BannedCollection::make($results->paginate(20));
    }

    public function banIp(Request $request)
    {
        $this->validate($request, [
            'ipv4'    => 'required|ipv4',
            'type'    => ['required', 'int', Rule::in([BannedIp::TYPE_TRANSACTION])],
            'note'    => 'nullable|string'
        ]);

        $user = auth()->user();

        $this->permissionUtil->abortForbiddenIfPermissionDenied(auth()->user(), Permission::ADMIN_MANAGE_BANNED_IP);

        try {
            BannedIp::firstOrCreate([
                'ipv4' => $request->ipv4,
                'type' => $request->type,
                'note' => $request->note
            ]);
            return response()->noContent();
        } catch (Throwable $throw) {
            abort(Response::HTTP_FORBIDDEN, "{$request->ipv4} 已加入黑名单");
        }
    }

    public function allowIp(Request $request, $ipv4)
    {
        $user = auth()->user();

        $this->permissionUtil->abortForbiddenIfPermissionDenied(auth()->user(), Permission::ADMIN_MANAGE_BANNED_IP);

        BannedIp::where([
            'ipv4' => ip2long($ipv4),
            'type' => $request->type
        ])->delete();

        return response()->noContent();
    }

    public function updateIpNote(Request $request)
    {
        $user = auth()->user();

        $this->permissionUtil->abortForbiddenIfPermissionDenied(auth()->user(), Permission::ADMIN_MANAGE_BANNED_IP);

        $ipv4 = BannedIp::where('id', $request->id)->first();
        $ipv4->update([
            'note' => $request->note
        ]);

        return response()->noContent();
    }

    public function getBanRealname(Request $request)
    {
        $this->validate($request, [
            'realname'    => 'nullable|string',
            'type'    => ['nullable', 'int', Rule::in([BannedRealname::TYPE_TRANSACTION, BannedRealname::TYPE_WITHDRAW])],
        ]);

        $user = auth()->user();

        $results = BannedRealname::where('type', $request->type)->orderByDesc('created_at');

        $results->when($request->realname, function ($builder, $name) {
            $builder->where('realname', 'like', "%$name%");
        });

        return BannedCollection::make($results->paginate(20));
    }

    public function banRealname(Request $request)
    {
        $this->validate($request, [
            'realname'    => 'required',
            'type'        => ['required', 'int', Rule::in([BannedRealname::TYPE_TRANSACTION, BannedRealname::TYPE_WITHDRAW])],
            'note'        => 'nullable|string'
        ]);

        $user = auth()->user();

        $this->permissionUtil->abortForbiddenIfPermissionDenied(auth()->user(), Permission::ADMIN_MANAGE_BANNED_IP);

        try {
            BannedRealname::firstOrCreate([
                'realname' => $request->realname,
                'type'     => $request->type,
                'note'     => $request->note
            ]);
            return response()->noContent();
        } catch (Throwable $throw) {
            abort(Response::HTTP_FORBIDDEN, "{$request->realname} 已加入黑名单");
        }
    }

    public function allowRealname(Request $request, $realname)
    {
        $user = auth()->user();

        $this->permissionUtil->abortForbiddenIfPermissionDenied(auth()->user(), Permission::ADMIN_MANAGE_BANNED_IP);

        BannedRealname::where([
            'realname' => $realname,
            'type' => $request->type
        ])->delete();

        return response()->noContent();
    }

    public function updateRealnameNote(Request $request)
    {
        $user = auth()->user();

        $this->permissionUtil->abortForbiddenIfPermissionDenied(auth()->user(), Permission::ADMIN_MANAGE_BANNED_IP);

        $realname = BannedRealname::where('id', $request->id)->first();
        $realname->update([
            'note' => $request->note
        ]);

        return response()->noContent();
    }
}
