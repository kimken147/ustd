<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Models\User;
use \App\Http\Resources\UserCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{

    public function show($username)
    {
        $user = User::where([
            'role'     => User::ROLE_PROVIDER,
            'username' => $username,
            'status'   => User::STATUS_ENABLE,
        ])->first();

        abort_if(
            !$user,
            Response::HTTP_BAD_REQUEST,
            '查无使用者'
        );

        return response()->json([
            'data' => [
                'id'       => $user->getKey(),
                'name'     => $user->name,
                'username' => $user->username,
            ],
        ]);
    }

    public function descendants(Request $request)
    {
        if ($request->exclude_self) {
            $users = User::descendantsOf(auth()->id());
        } else {
            $users = User::descendantsAndSelf(auth()->id());
        }

        return UserCollection::make($users);
    }

    public function updateControlDownlines (Request $request, User $provider)
    {
        if (!$provider->control_downline) {
            return $provider;
        }

        $downlines = [];
        foreach ($request->input('downlines') as $id) {
            $downlines[] = ['parent_id' => $provider->id, 'downline_id' => $id];
        }
        try {
            DB::beginTransaction();
            DB::table('control_downlines')->where('parent_id', $provider->id)->delete();
            DB::table('control_downlines')->insert($downlines);
            DB::commit();
        } catch (Throwable $throw) {
            DB::rollback();
        }
        return $provider;
    }
}
