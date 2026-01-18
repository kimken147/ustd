<?php

namespace App\Services\Transaction\Exceptions;

use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use Exception;
use Illuminate\Http\JsonResponse;

class TransactionValidationException extends Exception
{
    private ThirdPartyErrorResponse $errorResponse;

    public function __construct(ThirdPartyErrorResponse $errorResponse)
    {
        $this->errorResponse = $errorResponse;

        parent::__construct($errorResponse->message, $errorResponse->errorCode);
    }

    public function toThirdPartyResponse(): JsonResponse
    {
        return $this->errorResponse->toResponse();
    }

    public function getErrorResponse(): ThirdPartyErrorResponse
    {
        return $this->errorResponse;
    }
}
