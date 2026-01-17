<?php

namespace App\Services\Maya;

use App\Exceptions\PaymayaResponseError;
use Hamcrest\Core\IsNull;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayMayaApiService
{
    public string $channel = "pay_maya_api_service";
    public string $domain;
    public array $client;
    public array $chd;
    public array $auth;
    public HeaderDataService $headerDataService;
    public array $headers;

    public function __construct(HeaderDataService $headerDataService)
    {
        $this->headerDataService = $headerDataService;
        $apiList = config("pay.maya.api");
        $this->client = $apiList["client"];
        $this->chd = $apiList["chd"];
        $this->auth = $apiList["auth"];
        $this->domain = env("PAY_MAYA_URL");
    }

    public function executeBankTransfer(
        string $bearer,
        string $appToken,
        string $replace
    ) {
        $this->headers = $this->headerDataService->getBearerHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->chd[__FUNCTION__];
        $urlData["url"] = str_replace(
            ":transactionId",
            $replace,
            $urlData["url"]
        );
        return $this->sendApi($urlData, "{}");
    }

    public function otpForInstapayVerify(
        string $bearer,
        string $appToken,
        array $data,
        string $replace
    ) {
        $this->headers = $this->headerDataService->getBearerHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->client[__FUNCTION__];
        $urlData["url"] = str_replace(
            ":challengeId",
            $replace,
            $urlData["url"]
        );
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this->sendApi($urlData, $data);
    }

    public function otpForInstapayStart(
        string $bearer,
        string $appToken,
        array $data,
        string $replace
    ) {
        $this->headers = $this->headerDataService->getBearerHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->client[__FUNCTION__];
        $urlData["url"] = str_replace(
            ":challengeId",
            $replace,
            $urlData["url"]
        );
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this->sendApi($urlData, $data);
    }

    public function createBankTransfer(
        string $bearer,
        string $appToken,
        array $data
    ) {
        $this->headers = $this->headerDataService->getWholeHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->chd[__FUNCTION__];
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this->sendApi($urlData, $data);
    }

    public function executeP2pTransfers(
        string $bearer,
        string $appToken,
        string $replace
    ) {
        $this->headers = $this->headerDataService->getWholeHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->chd[__FUNCTION__];
        $urlData["url"] = str_replace(
            ":transactionId",
            $replace,
            $urlData["url"]
        );
        return $this->sendApi($urlData, "{}");
    }

    public function createP2pTransfer(
        string $bearer,
        string $appToken,
        array $data
    ) {
        $this->headers = $this->headerDataService->getWholeHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->chd[__FUNCTION__];
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this->sendApi($urlData, $data);
    }

    public function apiChangeAccountPassword(
        string $bearer,
        string $appToken,
        array $data
    ) {
        $this->headers = $this->headerDataService->getBearerHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->client[__FUNCTION__];
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this->sendApi($urlData, $data);
    }

    public function apiGetAccountBalance(string $bearer, string $appToken)
    {
        $this->headers = $this->headerDataService->getBearerHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->client[__FUNCTION__];
        return $this->sendApi($urlData);
    }

    public function apiChangeAccountEmail(
        string $bearer,
        string $appToken,
        array $data
    ) {
        $this->headers = $this->headerDataService->getBearerHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->client[__FUNCTION__];
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this->sendApi($urlData, $data);
    }

    public function apiVerifyEmail(string $bearer, string $appToken)
    {
        $this->headers = $this->headerDataService->getBearerHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->client[__FUNCTION__];
        return $this->sendApi($urlData, "");
    }

    public function apiGetAccountLimit(string $bearer, string $appToken)
    {
        $this->headers = $this->headerDataService->getBearerHeaders(
            $bearer,
            $appToken
        );
        $urlData = $this->client[__FUNCTION__];
        return $this->sendApi($urlData);
    }

    public function challengesLoginVerify(
        string $bearer,
        array $data,
        string $replace
    ) {
        $this->headers = $this->headerDataService->getBearerHeaders($bearer);
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $urlData = $this->client[__FUNCTION__];
        $urlData["url"] = str_replace(
            ":challengeId",
            $replace,
            $urlData["url"]
        );
        return $this->sendApi($urlData, $data);
    }

    public function challengesLoginStart(
        string $bearer,
        array $data,
        string $replace
    ) {
        $this->headers = $this->headerDataService->getBearerHeaders($bearer);
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $urlData = $this->client[__FUNCTION__];
        $urlData["url"] = str_replace(
            ":challengeId",
            $replace,
            $urlData["url"]
        );
        return $this->sendApi($urlData, $data);
    }

    public function sessions(string $bearer, array $data)
    {
        $this->headers = $this->headerDataService->getWholeHeaders($bearer);
        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        return $this->sendApi($this->client[__FUNCTION__], $data);
    }

    public function accessToken()
    {
        $this->headers = $this->headerDataService->getBaseHeaders();
        $queryData = http_build_query($this->headerDataService->getData());
        return $this->sendApi($this->auth[__FUNCTION__], $queryData);
    }

    public function sendApi(array $urlData, $data = [])
    {
        $url = $this->domain . $urlData["url"];
        $method = $urlData["method"];
        $contentType = $urlData["contentType"];

        // echo 'url:'.$url;
        // echo 'method:'.$method;
        // echo 'contentType:'.$contentType;
        // print_r($this->headers);
        // echo 'queryData:'.(count($data) == 0 ? '[]' : $data);
        // echo '-------------------------------';

        if ($method == "get") {
            $response = Http::withHeaders($this->headers)->get($url);
        } else {
            $response = Http::withHeaders($this->headers)
                ->withBody($data, $contentType)
                ->$method($url);
        }

        Log::debug(__FUNCTION__, [
            "url" => $url,
            "method" => $method,
            "contentType" => $contentType,
            "headers" => $this->headers,
            "queryData" => $data,
            "response" => $response->body(),
        ]);
        return $this->response($response);
    }

    public function response($response)
    {
        $responseBody = $response->body();
        $responseJson = json_decode(
            !is_null($responseBody) && $responseBody != ""
                ? $responseBody
                : "{}",
            true
        );
        if (!is_array($responseJson)) {
            throw new PaymayaResponseError($responseBody);
        }
        return $responseJson;
    }
}
