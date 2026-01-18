<?php

namespace App\Services\Transaction\DTO;

use Illuminate\Http\Request;

class DemoContext
{
    public function __construct(
        public readonly string $channelCode,
        public readonly string $username,
        public readonly string $secretKey,
        public readonly string $amount,
        public readonly string $orderNumber,
        public readonly string $notifyUrl,
        public readonly ?string $returnUrl = null,
        public readonly ?string $realName = null,
        public readonly ?string $clientIp = null,
        public readonly ?string $usdtRate = null,
        public readonly ?string $bankName = null,
        public readonly ?bool $matchLastAccount = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            channelCode: $request->channel_code,
            username: $request->username,
            secretKey: $request->secret_key,
            amount: $request->amount,
            orderNumber: $request->order_number,
            notifyUrl: $request->notify_url,
            returnUrl: $request->return_url,
            realName: $request->real_name,
            clientIp: $request->client_ip,
            usdtRate: $request->usdt_rate,
            bankName: $request->bank_name,
            matchLastAccount: $request->match_last_account,
        );
    }
}
