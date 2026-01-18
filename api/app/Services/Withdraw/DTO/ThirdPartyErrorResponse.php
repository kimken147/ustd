<?php

namespace App\Services\Withdraw\DTO;

use App\Utils\ThirdPartyResponseUtil;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ThirdPartyErrorResponse
{
    public function __construct(
        public readonly int $httpStatusCode,
        public readonly int $errorCode,
        public readonly string $message,
    ) {}

    public function toResponse(): JsonResponse
    {
        return response()->json([
            'http_status_code' => $this->httpStatusCode,
            'error_code' => $this->errorCode,
            'message' => $this->message,
        ]);
    }

    public static function badRequest(int $errorCode, string $message): self
    {
        return new self(Response::HTTP_BAD_REQUEST, $errorCode, $message);
    }

    public static function forbidden(int $errorCode, string $message): self
    {
        return new self(Response::HTTP_FORBIDDEN, $errorCode, $message);
    }

    public static function userNotFound(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_USER_NOT_FOUND,
            __('common.User not found')
        );
    }

    public static function invalidSign(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_SIGN,
            __('common.Signature error')
        );
    }

    public static function invalidIp(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_IP,
            __('common.Please contact admin to add IP to whitelist')
        );
    }

    public static function duplicateOrderNumber(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_DUPLICATE_ORDER_NUMBER,
            __('common.Duplicate number')
        );
    }

    public static function insufficientBalance(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INSUFFICIENT_BALANCE,
            __('wallet.InsufficientAvailableBalance')
        );
    }

    public static function withdrawDisabled(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_WITHDRAW_DISABLED,
            __('user.Withdraw disabled')
        );
    }

    public static function agencyWithdrawDisabled(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_AGENCY_WITHDRAW_DISABLED,
            __('user.Agency withdraw disabled')
        );
    }

    public static function invalidMinAmount(string $amount): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
            __('common.Amount below minimum: :amount', ['amount' => $amount])
        );
    }

    public static function invalidMaxAmount(string $amount): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_MAX_AMOUNT,
            __('common.Amount above maximum: :amount', ['amount' => $amount])
        );
    }

    public static function decimalNotAllowed(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_INVALID_MIN_AMOUNT,
            __('common.Decimal amount not allowed')
        );
    }

    public static function bankNotSupported(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_BANK_NOT_FOUND,
            __('common.Bank not supported')
        );
    }

    public static function forbiddenCardHolder(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_FORBIDDEN_NAME,
            __('common.Card holder access forbidden')
        );
    }

    public static function missingParameter(string $attribute): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_BAD_REQUEST,
            __('common.Information is incorrect: :attribute', ['attribute' => $attribute])
        );
    }

    public static function raceCondition(): self
    {
        return self::badRequest(
            ThirdPartyResponseUtil::ERROR_CODE_RACE_CONDITION,
            __('common.Conflict! Please try again later')
        );
    }
}
