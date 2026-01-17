<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ApiDocumentDownloadController extends Controller
{

    /**
     * Handle the incoming request.
     *
     * @param  Request  $request
     * @return Response
     */
    public function __invoke(Request $request)
    {
        if (config('filesystems.api-document-download-path')) {
            return redirect(config('filesystems.api-document-download-path'));
        }

        return response(__('common.Please contact admin'));
    }
}
