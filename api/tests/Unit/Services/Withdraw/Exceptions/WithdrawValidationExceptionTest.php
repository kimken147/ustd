<?php

namespace Tests\Unit\Services\Withdraw\Exceptions;

use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;
use Illuminate\Http\JsonResponse;
use Tests\TestCase;

class WithdrawValidationExceptionTest extends TestCase
{
    // ──────────────────────────────────────────────────
    // Constructor
    // ──────────────────────────────────────────────────

    public function test_constructor_sets_message_and_code(): void
    {
        $errorResponse = new ThirdPartyErrorResponse(
            httpStatusCode: 400,
            errorCode: 1001,
            message: 'Something went wrong',
        );

        $exception = new WithdrawValidationException($errorResponse);

        $this->assertSame('Something went wrong', $exception->getMessage());
        $this->assertSame(1001, $exception->getCode());
    }

    public function test_constructor_defaults_to_non_third_party(): void
    {
        $errorResponse = new ThirdPartyErrorResponse(
            httpStatusCode: 400,
            errorCode: 1001,
            message: 'Error',
        );

        $exception = new WithdrawValidationException($errorResponse);

        $this->assertFalse($exception->isThirdParty());
    }

    // ──────────────────────────────────────────────────
    // Factory methods
    // ──────────────────────────────────────────────────

    public function test_from_third_party_factory(): void
    {
        $errorResponse = new ThirdPartyErrorResponse(
            httpStatusCode: 403,
            errorCode: 2002,
            message: 'Third party error',
        );

        $exception = WithdrawValidationException::fromThirdParty($errorResponse);

        $this->assertTrue($exception->isThirdParty());
        $this->assertSame($errorResponse, $exception->getErrorResponse());
        $this->assertSame('Third party error', $exception->getMessage());
        $this->assertSame(2002, $exception->getCode());
    }

    public function test_from_merchant_factory(): void
    {
        $exception = WithdrawValidationException::fromMerchant('Merchant error', 400);

        $this->assertFalse($exception->isThirdParty());
        $this->assertSame('Merchant error', $exception->getMessage());
        $this->assertSame(400, $exception->getCode());
    }

    public function test_from_merchant_default_code(): void
    {
        $exception = WithdrawValidationException::fromMerchant('Default code error');

        $this->assertSame(400, $exception->getCode());
        $this->assertSame('Default code error', $exception->getMessage());
    }

    // ──────────────────────────────────────────────────
    // Accessors
    // ──────────────────────────────────────────────────

    public function test_get_error_response_returns_original(): void
    {
        $errorResponse = new ThirdPartyErrorResponse(
            httpStatusCode: 400,
            errorCode: 3003,
            message: 'Original response',
        );

        $exception = new WithdrawValidationException($errorResponse);

        $this->assertSame($errorResponse, $exception->getErrorResponse());
    }

    public function test_to_third_party_response_returns_json_response(): void
    {
        $errorResponse = new ThirdPartyErrorResponse(
            httpStatusCode: 400,
            errorCode: 4004,
            message: 'JSON response test',
        );

        $exception = new WithdrawValidationException($errorResponse);

        $result = $exception->toThirdPartyResponse();

        $this->assertInstanceOf(JsonResponse::class, $result);

        $data = $result->getData(true);
        $this->assertSame(400, $data['http_status_code']);
        $this->assertSame(4004, $data['error_code']);
        $this->assertSame('JSON response test', $data['message']);
    }
}
