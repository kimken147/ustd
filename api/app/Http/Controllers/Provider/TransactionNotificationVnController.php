<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessVnTransactionNotification;
use App\Models\Device;
use App\Models\UserChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TransactionNotificationVnController extends Controller
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
            'payload'   => 'required|string',
        ]);

        $payload = $request->payload;

        ProcessVnTransactionNotification::dispatch($request->accounts, $payload);

        return response()->json(null, Response::HTTP_CREATED);
    }
}
