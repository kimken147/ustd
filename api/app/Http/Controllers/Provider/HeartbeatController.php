<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Model\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class HeartbeatController extends Controller
{

    public function __invoke(Request $request)
    {
        $this->validate($request, [
            'device_id' => 'required|int',
            'now'       => 'required|date',
        ]);

        $device = Device::where('user_id', auth()->user()->getKey())->findOrFail($request->device_id);

        abort_if(!$device, Response::HTTP_BAD_REQUEST, __('device.Not found'));

        $now = Carbon::make($request->now)->tz(config('app.timezone'));

        Device::where('id', $device->getKey())
            ->where(function (Builder $devices) use ($now) {
                $devices->whereNull('last_heartbeat_at')
                    ->orWhere('last_heartbeat_at', '<', $now);
            })
            ->update(['last_heartbeat_at' => $now]);

        $device->refresh();

        return \App\Http\Resources\Provider\Device::make($device)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
