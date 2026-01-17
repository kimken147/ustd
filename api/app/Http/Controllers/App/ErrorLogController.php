<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ErrorLogController extends Controller
{
    public function store(Request $request)
    {
        Log::debug(__METHOD__, $request->all());

        return response()->json(null, Response::HTTP_CREATED);
    }
}
