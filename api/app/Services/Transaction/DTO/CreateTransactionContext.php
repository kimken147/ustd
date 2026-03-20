<?php

namespace App\Services\Transaction\DTO;

use Illuminate\Http\Request;

class CreateTransactionContext
{
    public function __construct(
        public readonly string $channelCode,
        public readonly string $username,
        public readonly string $amount,
        public readonly string $orderNumber,
        public readonly string $notifyUrl,
        public readonly string $sign,
        public readonly ?string $clientIp = null,
        public readonly ?string $realName = null,
        public readonly ?string $returnUrl = null,
        public readonly ?string $bankName = null,
        public readonly ?string $usdtRate = null,
        public readonly ?bool $matchLastAccount = null,
        public readonly bool $isThirdParty = false,
    ) {}

    public static function fromViewRequest(Request $request): self
    {
        return new self(
            channelCode: $request->channel_code,
            username: $request->username,
            amount: $request->amount,
            orderNumber: $request->order_number,
            notifyUrl: urldecode($request->notify_url),
            sign: $request->sign,
            clientIp: $request->input('client_ip'),
            realName: $request->real_name ? urldecode($request->real_name) : null,
            returnUrl: $request->return_url,
            bankName: $request->bank_name,
            usdtRate: $request->usdt_rate,
            matchLastAccount: $request->match_last_account,
            isThirdParty: false,
        );
    }

    public static function fromThirdPartyRequest(Request $request): self
    {
        return new self(
            channelCode: $request->channel,
            username: $request->merchant_id,
            amount: $request->input('amount'),
            orderNumber: $request->input('out_trade_no'),
            notifyUrl: $request->input('callback_url'),
            sign: $request->input('sign'),
            clientIp: $request->input('client_ip'),
            realName: $request->input('payer_name'),
            returnUrl: $request->redirect_url,
            bankName: $request->network,
            usdtRate: $request->usdt_rate,
            matchLastAccount: $request->match_last_account,
            isThirdParty: true,
        );
    }
}
