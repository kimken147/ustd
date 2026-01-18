<?php

namespace App\Services\Transaction\DTO;

class DemoResult
{
    public function __construct(
        public readonly string $url,
    ) {}
}
