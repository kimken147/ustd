<?php

namespace Tests\Unit\Services\Withdraw\DTO;

use App\Services\Withdraw\DTO\ThirdPartyErrorResponse;
use App\Utils\ThirdPartyResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ThirdPartyErrorResponseTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $response = new ThirdPartyErrorResponse(
            httpStatusCode: 400,
            errorCode: 99,
            message: 'Something went wrong',
        );

        $this->assertSame(400, $response->httpStatusCode);
        $this->assertSame(99, $response->errorCode);
        $this->assertSame('Something went wrong', $response->message);
    }

    public function test_to_response_returns_json_response(): void
    {
        $response = new ThirdPartyErrorResponse(
            httpStatusCode: 400,
            errorCode: 5,
            message: 'Invalid signature',
        );

        $jsonResponse = $response->toResponse();

        $this->assertInstanceOf(JsonResponse::class, $jsonResponse);

        $data = $jsonResponse->getData(true);

        $this->assertSame(400, $data['http_status_code']);
        $this->assertSame(5, $data['error_code']);
        $this->assertSame('Invalid signature', $data['message']);
    }

    public function test_bad_request_creates_400_response(): void
    {
        $response = ThirdPartyErrorResponse::badRequest(42, 'Bad request error');

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->httpStatusCode);
        $this->assertSame(42, $response->errorCode);
        $this->assertSame('Bad request error', $response->message);
    }

    public function test_forbidden_creates_403_response(): void
    {
        $response = ThirdPartyErrorResponse::forbidden(7, 'Forbidden error');

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->httpStatusCode);
        $this->assertSame(7, $response->errorCode);
        $this->assertSame('Forbidden error', $response->message);
    }

    #[DataProvider('factoryMethodsProvider')]
    public function test_factory_method_returns_correct_response(
        string $method,
        int $expectedErrorCode,
        array $args,
    ): void {
        /** @var ThirdPartyErrorResponse $response */
        $response = ThirdPartyErrorResponse::$method(...$args);

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->httpStatusCode);
        $this->assertSame($expectedErrorCode, $response->errorCode);
        $this->assertIsString($response->message);
        $this->assertNotEmpty($response->message);
    }

    public static function factoryMethodsProvider(): array
    {
        return [
            'userNotFound' => ['userNotFound', ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND, []],
            'invalidSign' => ['invalidSign', ThirdPartyResponseUtil::ERROR_CODE_INVALID_SIGN, []],
            'invalidIp' => ['invalidIp', ThirdPartyResponseUtil::ERROR_CODE_INVALID_IP, []],
            'duplicateOrderNumber' => ['duplicateOrderNumber', ThirdPartyResponseUtil::ERROR_CODE_DUPLICATE_ORDER_NUMBER, []],
            'insufficientBalance' => ['insufficientBalance', ThirdPartyResponseUtil::ERROR_CODE_INSUFFICIENT_BALANCE, []],
            'withdrawDisabled' => ['withdrawDisabled', ThirdPartyResponseUtil::ERROR_CODE_WITHDRAW_DISABLED, []],
            'agencyWithdrawDisabled' => ['agencyWithdrawDisabled', ThirdPartyResponseUtil::ERROR_CODE_AGENCY_WITHDRAW_DISABLED, []],
            'invalidMinAmount' => ['invalidMinAmount', ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT, ['100']],
            'invalidMaxAmount' => ['invalidMaxAmount', ThirdPartyResponseUtil::ERROR_CODE_INVALID_MAX_AMOUNT, ['500']],
            'decimalNotAllowed' => ['decimalNotAllowed', ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT, []],
            'bankNotSupported' => ['bankNotSupported', ThirdPartyResponseUtil::ERROR_CODE_BANK_NOT_FOUND, []],
            'forbiddenCardHolder' => ['forbiddenCardHolder', ThirdPartyResponseUtil::ERROR_CODE_FORBIDDEN_NAME, []],
            'missingParameter' => ['missingParameter', ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST, ['username']],
            'raceCondition' => ['raceCondition', ThirdPartyResponseUtil::ERROR_CODE_RACE_CONDITION, []],
            'channelNotFound' => ['channelNotFound', ThirdPartyResponseUtil::ERROR_CODE_INVALID_CHANNEL_CODE, []],
            'channelMaintenance' => ['channelMaintenance', ThirdPartyResponseUtil::ERROR_CODE_CHANNEL_TEMPORARY_UNAVAILABLE, []],
            'channelUnavailable' => ['channelUnavailable', ThirdPartyResponseUtil::ERROR_CODE_CHANNEL_TEMPORARY_UNAVAILABLE, []],
            'userDisabled' => ['userDisabled', ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND, []],
            'transactionDisabled' => ['transactionDisabled', ThirdPartyResponseUtil::ERROR_CODE_TRANSACTION_DISABLED, []],
            'balanceLimitExceeded' => ['balanceLimitExceeded', ThirdPartyResponseUtil::ERROR_CODE_INSUFFICIENT_BALANCE, []],
            'ipBanned' => ['ipBanned', ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST, []],
            'realnameBanned' => ['realnameBanned', ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST, []],
            'rateLimitExceeded' => ['rateLimitExceeded', ThirdPartyResponseUtil::ERROR_CODE_BUSY_PAYING, []],
            'matchingTimeout' => ['matchingTimeout', ThirdPartyResponseUtil::ERROR_CODE_NO_AVAILABLE_USER_CHANNEL_ACCOUNT_FOR_TRANSACTION, []],
            'invalidAmount' => ['invalidAmount', ThirdPartyResponseUtil::ERROR_CODE_INVALID_AMOUNT, []],
            'realNameRequired' => ['realNameRequired', ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST, []],
        ];
    }
}
