<?php

namespace App\Exceptions;

use Exception;

class PaymayaResponseMissingParametersError extends Exception
{
    public function report()
    {
        //
    }

    public function render($request)
    {
        $errorResponse = [
            'error' => [
                'code'      => -1,
                'msg'       => 'Missing parameters.',
                'request'   => $request->all(),
            ]
        ];
        return response(json_encode($errorResponse, JSON_UNESCAPED_UNICODE));
    }
}
