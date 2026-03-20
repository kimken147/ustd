<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiDocumentController extends Controller
{
    public function __invoke(Request $request)
    {
        if ($request->isMethod('post')) {
            if ($request->input('password') === '666888') {
                session(['api_doc_verified' => true]);
                return redirect()->route('v1.api-document');
            }
            return redirect()->route('v1.api-document')->withErrors(['password' => '密码错误，请重新输入']);
        }

        $verified = session('api_doc_verified', false);
        $region = strtolower(config('app.region', 'cn'));
        $banks = [];
        $banksFile = database_path("data/banks/usdt.php");
        if (file_exists($banksFile)) {
            $banks = array_column(require $banksFile, 'name');
        }

        return view('v1.api-document', compact('verified', 'region', 'banks'));
    }
}
