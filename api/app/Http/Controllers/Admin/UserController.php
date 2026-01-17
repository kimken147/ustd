<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListUserRequest;
use App\Http\Resources\UserCollection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class UserController extends Controller
{

    public function index(ListUserRequest $request)
    {
        $users = User::query()->where('god', false);

        $users->when($request->ids, function ($users, $ids) use ($request) {
            $users->whereIn('id', $ids);

            $request->merge([
                'no_paginate' => 1,
            ]);
        });

        $users->when($request->name_or_username, function ($users, $nameOrUsername) {
            $users->where(function ($users) use ($nameOrUsername) {
                $users->where('name', 'like', "%$nameOrUsername%")
                    ->orWhere('username', $nameOrUsername);
            });
        });

        $users->when($request->name_or_fuzzy_username, function ($users, $nameOrUsername) {
            $users->where(function ($users) use ($nameOrUsername) {
                $users->where('name', 'like', "%$nameOrUsername%")
                    ->orWhere('username', 'like', "%$nameOrUsername%");
            });
        });

        $users->when($request->ipv4, function ($builder, $ipv4) use ($request) {
            $builder->whereHas('whitelistedIps', function (Builder $whitelistedIps) use ($ipv4, $request) {
                $whitelistedIps->where('ipv4', ip2long($ipv4));
            });
        });

        $users->when($request->role, function ($builder, $role) {
            $builder->where(function ($builder) use ($role) {
                $builder->where('role', $role)
                    ->orWhereHas('parent', function ($builder) use ($role) {
                        $builder->where('role', $role);
                    });
            });
        });

        $users->when($request->root_only, function ($builder) {
            $builder->whereIsRoot();
        });

        $users->when($request->status, function ($builder, $status) {
            $builder->where('status', $status);
        });

        $users->when(!is_null($request->agent_enable), function ($builder) use ($request) {
            $builder->where('agent_enable', $request->agent_enable);
        });

        $users->when($request->input('include'), function (Builder $builder, $include) use ($request) {
            $include = collect($include)->mapWithKeys(function ($item) {
                $item = Str::camel($item);

                return [$item => $item];
            })->only(['whitelistedIps'])->mapWithKeys(function ($item, $key) use ($request) {
                if ($item === 'whitelistedIps') {
                    return ['whitelistedIps' => function ($builder) use ($request) {
                        $builder->when($request->whitelisted_ip_type, function ($builder, $type) {
                            $builder->where('type', $type);
                        });
                    }];
                }

                return [$key => $item];
            });

            $builder->with($include->toArray());
        });

        $users = $request->no_paginate ? $users->get() : $users->paginate(20)->appends($request->query->all());

        return UserCollection::make($users);
    }

    public function show(User $user)
    {
        return \App\Http\Resources\User::make($user->load('parent'));
    }
}
