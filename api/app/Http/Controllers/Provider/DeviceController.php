<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\DeviceCollection;
use App\Model\Device;
use App\Model\FeatureToggle;
use App\Model\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class DeviceController extends Controller
{

    public function batchUpdateAll(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        $this->validate($request, [
            'regular_customer_first' => 'required|boolean',
        ]);

        if ($request->regular_customer_first) {
            $minRequiredDeviceRegularCustomerCount = $featureToggleRepository->enabled(FeatureToggle::FEATURE_MIN_COUNT_REGULAR_CUSTOMER_ENABLED)
                ? $featureToggleRepository->valueOf(FeatureToggle::FEATURE_MIN_COUNT_REGULAR_CUSTOMER_ENABLED)
                : 0;

            DB::transaction(function () use ($minRequiredDeviceRegularCustomerCount, $request) {
                auth()->user()->devices()
                    ->whereHas('deviceRegularCustomers', function (Builder $deviceRegularCustomers) {
                        $deviceRegularCustomers->where('updated_at', '>=', now()->subHours(24));
                    }, '>=', $minRequiredDeviceRegularCustomerCount)
                    ->update(['regular_customer_first' => $request->regular_customer_first]);

                auth()->user()->userChannelAccounts()
                    ->whereHas('device.deviceRegularCustomers', function (Builder $deviceRegularCustomers) {
                        $deviceRegularCustomers->where('updated_at', '>=', now()->subHours(24));
                    }, '>=', $minRequiredDeviceRegularCustomerCount)
                    ->update(['regular_customer_first' => $request->regular_customer_first]);
            });
        } else {
            DB::transaction(function () use ($request) {
                auth()->user()->devices()
                    ->update(['regular_customer_first' => $request->regular_customer_first]);

                auth()->user()->userChannelAccounts()
                    ->update(['regular_customer_first' => $request->regular_customer_first]);
            });
        }

        return response()->json(null);
    }

    public function destroy(Device $device)
    {
        abort_if($device->user_id !== auth()->user()->getKey(), Response::HTTP_NOT_FOUND);

        DB::transaction(function () use ($device) {
            $device->delete();

            $device->userChannelAccounts()->delete();
        });

        return response()->noContent();
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            'name'        => 'nullable|string',
            'no_paginate' => 'nullable|boolean',
        ]);

        if($request->no_paginate){
            $devices = Device::with('userChannelAccounts')->whereIn('id', UserChannelAccount::where('user_id', auth()->user()->getKey())->select(['device_id']))->latest()->get();

            if($devices->isEmpty()){
                $devices = Device::with('userChannelAccounts')->where('user_id', auth()->user()->getKey())->orderBy('id')->take(1)->get();
            }

            return DeviceCollection::make($devices);

        }else{
            $devices = Device::with('userChannelAccounts')->where('user_id', auth()->user()->getKey())->latest();

            $devices->when($request->name, function (Builder $devices, $name) {
                $devices->where('name', 'like', "%$name%");
            });

            return DeviceCollection::make($devices->paginate(20));
        }

    }

    public function show(Device $device)
    {
        abort_if($device->user_id !== auth()->user()->getKey(), Response::HTTP_NOT_FOUND);

        return \App\Http\Resources\Provider\Device::make($device);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string|max:255',
        ]);

        $device = auth()->user()->devices()->create([
            'name' => $request->name,
        ]);

        return \App\Http\Resources\Provider\Device::make($device);
    }

    public function update(Request $request, Device $device, FeatureToggleRepository $featureToggleRepository)
    {
        abort_if($device->user_id !== auth()->user()->getKey(), Response::HTTP_NOT_FOUND);

        $this->validate($request, [
            'name'                   => 'string|max:255',
            'regular_customer_first' => 'boolean',
        ]);

        if ($request->name) {
            try {
                $device->update(['name' => $request->name]);
            } catch (QueryException $e) {
                abort(Response::HTTP_FORBIDDEN, "{$request->name} 名称重复");
            }
        }

        if ($request->has('regular_customer_first')) {
            if ($request->regular_customer_first) {
                $minRequiredDeviceRegularCustomerCount = $featureToggleRepository->enabled(FeatureToggle::FEATURE_MIN_COUNT_REGULAR_CUSTOMER_ENABLED)
                    ? $featureToggleRepository->valueOf(FeatureToggle::FEATURE_MIN_COUNT_REGULAR_CUSTOMER_ENABLED)
                    : 0;

                abort_if(
                    $device->deviceRegularCustomers()->count() < $minRequiredDeviceRegularCustomerCount,
                    Response::HTTP_BAD_REQUEST,
                    __('device.Unable to enable regular_customer_first due to insufficient regular customers')
                );
            }

            DB::transaction(function () use ($device, $request) {
                $device->update(['regular_customer_first' => $request->regular_customer_first]);

                $device->userChannelAccounts()->update(['regular_customer_first' => $request->regular_customer_first]);
            });
        }

        return \App\Http\Resources\Provider\Device::make($device);
    }
}
