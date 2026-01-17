<?php

namespace App\Services\Maya;

use App\Services\Maya\HelperService;

class HeaderDataService
{
    public $helperService;

    public function __construct()
    {
        $this->helperService = new HelperService;
    }

    public function getBaseHeaders()
    {
        return array_merge(
            $this->helperService->getRequestHeaders(),
            [
                'source'                => $this->helperService->getPlatformType(),
                'user-agent'            => $this->helperService->getUserAgent(),
                'x-encoded-user-agent'  => $this->helperService->getEncodedUserAgent(),
                'request-reference-no'  => $this->helperService->getRequestId(),
            ]
        );
    }

    public function getData()
    {
        return [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->helperService->getEncryptedClient(),
            'client_secret' => $this->helperService->getEncryptedSecret(),
        ];
    }

    public function getBearerHeaders(string $bearerToken, string $token = '')
    {
        return array_merge(
            $this->getBaseHeaders(),
            [
                'authorization'         => 'Bearer ' . $bearerToken,
                'token'                 => $token,
            ]
        );
    }

    public function getWholeHeaders(string $bearerToken, string $token = '')
    {
        return array_merge(
            $this->getBearerHeaders($bearerToken, $token),
            [
                'client_os_name'        => $this->helperService->getPlatformType(),
                'client_app_version'    => $this->helperService->getApkVersion(),
                'cid'                   => $this->helperService->getClientId(),
                'session_id'            => $this->helperService->getSessionId(),
            ]
        );
    }
}
