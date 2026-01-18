<?php

namespace App\Services\Withdraw\Exceptions;

use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use Exception;
use Illuminate\Http\JsonResponse;

class WithdrawValidationException extends Exception
{
    private ThirdPartyErrorResponse $errorResponse;

    private bool $isThirdParty;

    public function __construct(
        ThirdPartyErrorResponse $errorResponse,
        bool $isThirdParty = false
    ) {
        $this->errorResponse = $errorResponse;
        $this->isThirdParty = $isThirdParty;

        parent::__construct($errorResponse->message, $errorResponse->errorCode);
    }

    public static function fromThirdParty(ThirdPartyErrorResponse $response): self
    {
        return new self($response, true);
    }

    public static function fromMerchant(string $message, int $code = 400): self
    {
        return new self(
            ThirdPartyErrorResponse::badRequest($code, $message),
            false
        );
    }

    public function isThirdParty(): bool
    {
        return $this->isThirdParty;
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
