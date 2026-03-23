<?php

namespace App\Utils;

use App\DTOs\TransactionParams;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;

class TransactionDataBuilder
{
    public function buildNormalDeposit(TransactionParams $params, User $provider): array
    {
        return [
            "from_id" => 0,
            "to_id" => $provider->getKey(),
            "to_wallet_id" => $provider->wallet->getKey(),
            "locked_by_id" => null,
            "client_ipv4" => $params->clientIpv4,
            "type" => Transaction::TYPE_NORMAL_DEPOSIT,
            "status" => Transaction::STATUS_PAYING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "from_account_mode" => null,
            "to_account_mode" => $provider->account_mode,
            "from_channel_account" => $params->bankCard->toFromChannelAccount(),
            "to_channel_account" => $params->toData ?: [],
            "amount" => $params->amount,
            "floating_amount" => $params->amount,
            "actual_amount" => 0,
            "channel_code" => null,
            "order_number" => $params->orderNumber,
            "note" => $params->note,
            "notify_url" => $params->notifyUrl,
            "usdt_rate" => $params->usdtRate ?? 0,
            "from_device_name" => null,
            "certificate_file_path" => null,
            "notified_at" => null,
            "matched_at" => null,
            "confirmed_at" => null,
            "locked_at" => null,
        ];
    }

    public function buildNormalWithdraw(TransactionParams $params, User $user): array
    {
        return [
            "parent_id" => optional($params->parent)->getKey(),
            "from_id" => $user->getKey(),
            "from_wallet_id" => $user->wallet->getKey(),
            "to_id" => 0,
            "locked_by_id" => null,
            "client_ipv4" => $params->clientIpv4,
            "type" => Transaction::TYPE_NORMAL_WITHDRAW,
            "sub_type" => $params->subType,
            "status" =>
                !$params->parent && $user->withdraw_review_enable
                    ? Transaction::STATUS_PENDING_REVIEW
                    : Transaction::STATUS_PAYING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "from_account_mode" => $user->account_mode,
            "to_account_mode" => null,
            "from_channel_account" => $params->bankCard->toFromChannelAccount(),
            "to_channel_account" => $params->toData ?: [],
            "amount" => $params->amount,
            "floating_amount" => $params->amount,
            "actual_amount" => 0,
            "usdt_rate" => $params->usdtRate ?? 0,
            "channel_code" => null,
            "order_number" => $params->orderNumber,
            "note" => $params->note,
            "notify_url" => $params->notifyUrl,
            "from_device_name" => null,
            "certificate_file_path" => null,
            "notified_at" => null,
            "matched_at" => null,
            "confirmed_at" => null,
            "locked_at" => null,
        ];
    }

    public function buildPaufenWithdraw(TransactionParams $params, User $merchant): array
    {
        return [
            "parent_id" => optional($params->parent)->getKey(),
            "from_id" => $merchant->getKey(),
            "from_wallet_id" => $merchant->wallet->getKey(),
            "to_id" => null,
            "locked_by_id" => null,
            "client_ipv4" => $params->clientIpv4,
            "type" => Transaction::TYPE_PAUFEN_WITHDRAW,
            "sub_type" => $params->subType,
            "status" =>
                !$params->parent && $merchant->withdraw_review_enable
                    ? Transaction::STATUS_PENDING_REVIEW
                    : Transaction::STATUS_MATCHING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "from_account_mode" => $merchant->account_mode,
            "to_account_mode" => null,
            "from_channel_account" => $params->bankCard->toFromChannelAccount(),
            "to_channel_account" => $params->toData ?: [],
            "amount" => $params->amount,
            "floating_amount" => $params->amount,
            "actual_amount" => 0,
            "usdt_rate" => $params->usdtRate ?? 0,
            "channel_code" => null,
            "order_number" => $params->orderNumber,
            "note" => $params->note,
            "notify_url" => $params->notifyUrl,
            "from_device_name" => null,
            "certificate_file_path" => null,
            "notified_at" => null,
            "matched_at" => null,
            "confirmed_at" => null,
            "locked_at" => null,
        ];
    }

    public function buildThirdchannelWithdraw(TransactionParams $params, User $user, $thirdchannelId = null): array
    {
        return [
            "parent_id" => optional($params->parent)->getKey(),
            "from_id" => $user->getKey(),
            "from_wallet_id" => $user->wallet->getKey(),
            "to_id" => 0,
            "locked_by_id" => null,
            "client_ipv4" => $params->clientIpv4,
            "type" => Transaction::TYPE_NORMAL_WITHDRAW,
            "sub_type" => $params->subType,
            "status" => Transaction::STATUS_THIRD_PAYING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "from_account_mode" => $user->account_mode,
            "to_account_mode" => null,
            "from_channel_account" => $params->bankCard->toFromChannelAccount(),
            "to_channel_account" => $params->toData ?: [],
            "amount" => $params->amount,
            "floating_amount" => $params->amount,
            "actual_amount" => 0,
            "usdt_rate" => $params->usdtRate ?? 0,
            "channel_code" => null,
            "order_number" => $params->orderNumber,
            "note" => $params->note,
            "notify_url" => $params->notifyUrl,
            "from_device_name" => null,
            "certificate_file_path" => null,
            "notified_at" => null,
            "matched_at" => null,
            "confirmed_at" => null,
            "locked_at" => null,
            "thirdchannel_id" => $thirdchannelId ?? null,
        ];
    }

    public function buildInternalTransfer(TransactionParams $params, ?UserChannelAccount $account = null, string $currency = 'USDT'): array
    {
        $data = [
            "from_id" => 0,
            "from_wallet_id" => 0,
            "to_id" => 0,
            "to_channel_account_id" => null,
            "type" => $currency === Transaction::CURRENCY_USDT
                ? Transaction::TYPE_INTERNAL_TRANSFER
                : Transaction::TYPE_NATIVE_TRANSFER,
            "currency" => $currency,
            "status" => Transaction::STATUS_MATCHING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "to_account_mode" => null,
            "from_channel_account" => $params->bankCard->toFromChannelAccount(false),
            "to_channel_account" => [],
            "amount" => $params->amount,
            "floating_amount" => $params->amount,
            "actual_amount" => 0,
            "usdt_rate" => $params->usdtRate ?? 0,
            "channel_code" => null,
            "order_number" => $params->orderNumber,
            "note" => $params->note,
        ];

        if ($account) {
            $data["to_id"] = $account->user_id;
            $data["to_channel_account_id"] = $account->id;
            $data["to_channel_account"] = array_merge($account->detail, [
                "channel_code" => $account->channel_code,
            ]);
            $data["status"] = Transaction::STATUS_PAYING;
            $data["matched_at"] = now();
        }

        return $data;
    }

    public function buildPaufenTransaction(TransactionParams $params, User $merchant, Channel $channel): array
    {
        $to = array_merge(
            [
                UserChannelAccount::DETAIL_KEY_REAL_NAME => $params->realName,
                "query" => json_encode(request()->all()),
                "binance_usdt_rate" => $params->binanceUsdtRate,
            ],
            $params->toData
        );

        return [
            "from_id" => null,
            "to_id" => $merchant->getKey(),
            "to_wallet_id" => $merchant->wallet->getKey(),
            "locked_by_id" => null,
            "client_ipv4" => $params->clientIpv4,
            "type" => Transaction::TYPE_PAUFEN_TRANSACTION,
            "status" => Transaction::STATUS_MATCHING,
            "notify_status" => Transaction::NOTIFY_STATUS_NONE,
            "from_account_mode" => null,
            "to_account_mode" => $merchant->account_mode,
            "from_channel_account" => [],
            "to_channel_account" => $to,
            "amount" => $params->amount,
            "floating_amount" => $params->floatingAmount
                ? $params->floatingAmount
                : $params->amount,
            "actual_amount" => 0,
            "channel_code" => $channel->getKey(),
            "order_number" => $params->orderNumber,
            "note" => $params->note,
            "notify_url" => $params->notifyUrl,
            "usdt_rate" => $params->usdtRate ?? 0,
            "from_device_name" => null,
            "certificate_file_path" => null,
            "notified_at" => null,
            "matched_at" => null,
            "confirmed_at" => null,
            "locked_at" => null,
        ];
    }
}
