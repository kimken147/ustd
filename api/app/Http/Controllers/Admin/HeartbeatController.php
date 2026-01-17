<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class HeartbeatController extends Controller
{

    public function __invoke(Request $request)
    {
        $now = Carbon::make($request->now)->tz(config('app.timezone'));

        auth()->user()->update(['last_activity_at' => $now]);

        return response()->setStatusCode(Response::HTTP_CREATED);
    }
}
