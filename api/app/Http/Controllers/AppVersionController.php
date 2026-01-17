<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppVersionController extends Controller
{

    public function __invoke(Request $request)
    {
        if ($request->app == 'game') {
            $setting = DB::table('app_settings')->find(2);
        } else {
            $setting = DB::table('app_settings')->find(1);
        }

        return response()->json([
            'version'          => optional($setting)->version,
            'app_download_url' => optional($setting)->download_url,
            'messages' => optional($setting)->messages,
        ]);
    }
}
