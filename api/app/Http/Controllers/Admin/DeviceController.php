<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DeviceCollection;
use App\Model\Device;
use App\Model\User;
use Illuminate\Http\Response;

class DeviceController extends Controller
{

    public function index(User $provider)
    {
        abort_if($provider->role !== User::ROLE_PROVIDER, Response::HTTP_NOT_FOUND);

        return DeviceCollection::make($provider->devices()->latest()->paginate(20));
    }

    public function show(User $provider, Device $device)
    {
        abort_if(
            $provider->role !== User::ROLE_PROVIDER
            || $device->user_id !== $provider->getKey(),
            Response::HTTP_NOT_FOUND
        );

        return \App\Http\Resources\Admin\Device::make($device);
    }
}
