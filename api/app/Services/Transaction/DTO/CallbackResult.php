<?php

namespace App\Services\Transaction\DTO;

class CallbackResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $responseBody = null,
        public readonly ?string $error = null,
        public readonly int $statusCode = 200,
    ) {}

    public static function success(?string $responseBody = 'SUCCESS'): self
    {
        return new self(true, $responseBody);
    }

    public static function fail(string $error, int $statusCode = 400): self
    {
        return new self(false, null, $error, $statusCode);
    }
}
