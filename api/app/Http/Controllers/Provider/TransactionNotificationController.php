<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessCnTransactionNotification;
use App\Jobs\ProcessPhTransactionNotification;
use App\Model\Device;
use App\Model\UserChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TransactionNotificationController extends Controller
{

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function __invoke(Request $request)
    {
        $this->validate($request, [
            'device_id' => 'required|int',
            'payload'   => 'required|string',
        ]);

        $device = Device::where('user_id', auth()->user()->getKey())->find($request->device_id);

        abort_if(!$device, Response::HTTP_BAD_REQUEST, __('device.Not found'));

        $payload = $request->payload;

        if (env('APP_REGION') == 'ph') {
            ProcessPhTransactionNotification::dispatch($device, $payload);
        } else {
            ProcessCnTransactionNotification::dispatch($device, $payload);
        }

        return response()->json(null, Response::HTTP_CREATED);
    }
}
