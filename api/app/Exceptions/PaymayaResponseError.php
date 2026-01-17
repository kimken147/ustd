<?php

namespace App\Exceptions;

use App\Services\Maya\LogService;
use Exception;

class PaymayaResponseError extends Exception
{
    public function report()
    {
        //
    }

    public function render()
    {
        $channel = "ResponseError";
        $function = __FUNCTION__;
        $logService = new LogService($channel, $function);
        $logService->writeResponseLog($this->getMessage());

        $msg = $this->getMessage();
        $msgJson = json_decode($msg, true);
        if (is_array($msgJson)) {
            $msg = is_array($msgJson["data"])
                ? json_encode($msgJson["data"], JSON_UNESCAPED_UNICODE)
                : $msgJson["data"];
        }

        return response($msg);
    }
}
