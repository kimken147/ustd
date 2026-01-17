<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\PermissionCollection;
use App\Model\Permission;
use App\Model\User;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        $this->validate($request, [
            'no_paginate' => 'nullable|boolean',
        ]);

        $permissions = Permission::where('role', User::ROLE_ADMIN);

        return PermissionCollection::make($request->no_paginate ? $permissions->get() : $permissions->paginate(20));
    }
}
