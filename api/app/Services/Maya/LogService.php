<?php

namespace App\Services\Maya;

use Illuminate\Support\Facades\Log;

class LogService
{
    public string $channel;
    public string $function;

    public function __construct($channel, $function)
    {
        $this->channel = $channel;
        $this->function = $function;
    }

    public function writeRequestLog($request, array $data = [])
    {
        $requestData = gettype($request) === 'object' ? $request->all() : [];
        Log::debug($this->function, [
            'request'       => $request,
            'requestData'   => $requestData,
            'data'          => $data
        ]);
    }

    public function writeResponseLog($data = '')
    {
        $responseData = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
        Log::debug($this->function, [
            'responseData' => $responseData,
        ]);
    }

    public function getErrorData($data = ''): string
    {
        $error = [
            'channel'   => $this->channel,
            'function'  => $this->function,
            'data'      => $data,
        ];
        return json_encode($error, JSON_UNESCAPED_UNICODE);
    }
}
