<?php

namespace App\Utils;

use App\DTOs\TransactionParams;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Services\Transaction\TransactionFeeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TransactionFactory
{
    private $transactionFeeService;

    public function __construct(
        TransactionFeeService $transactionFeeService
    ) {
        $this->transactionFeeService = $transactionFeeService;
    }

    public function normalDepositTo(TransactionParams $params, User $provider)
    {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
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
            ]);

            if ($params->note) {
                TransactionNote::create([
                    "transaction_id" => $transaction->getKey(),
                    "user_id" => $provider->realUser()->getKey(),
                    "note" => $params->note,
                ]);
            }

            $this->transactionFeeService->createDepositFees($transaction, $provider);

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    private function throwIfMissing(TransactionParams $params, array $attributes)
    {
        foreach ($attributes as $attribute) {
            if ($attribute === "bankCard") {
                if (
                    is_null($params->bankCard) ||
                    !($params->bankCard instanceof BankCardTransferObject)
                ) {
                    throw new RuntimeException("bankCard can not be empty");
                }

                foreach ($params->bankCard as $key => $bankCardProperty) {
                    if (in_array($key, ["bankProvince", "bankCity"])) {
                        continue;
                    }
                    throw_if(
                        is_null($bankCardProperty),
                        new RuntimeException($attribute . " can not be empty")
                    );
                }
            } else {
                throw_if(
                    is_null($params->$attribute),
                    new RuntimeException($attribute . " can not be empty")
                );
            }
        }
    }

    public function normalWithdrawFrom(
        TransactionParams $params,
        User $user,
        $agency = false,
        ?Transaction $parent = null,
        $type = "balance"
    ) {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
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
            ]);

            $this->transactionFeeService->createWithdrawFees(
                $transaction,
                $user,
                $agency,
                $parent,
                $type
            );

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    public function paufenWithdrawFrom(
        TransactionParams $params,
        User $merchant,
        $agency = false,
        ?Transaction $parent = null
    ) {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
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
            ]);

            $this->transactionFeeService->createWithdrawFees(
                $transaction,
                $merchant,
                $agency,
                $parent
            );

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();
            return null;
        }

        return $transaction;
    }

    public function thirdchannelWithdrawFrom(
        TransactionParams $params,
        User $user,
        $agency = false,
        ?Transaction $parent = null,
        $thirdchannel_id = null
    ) {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $transaction = Transaction::create([
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
                "thirdchannel_id" => $thirdchannel_id ?? null,
            ]);

            $this->transactionFeeService->createWithdrawFees($transaction, $user, $agency, $parent);

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    /**
     * Create an internal transfer transaction.
     *
     * @param TransactionParams $params Transaction parameters
     * @param UserChannelAccount|null $account Target channel account
     */
    public function internalTransferFrom(TransactionParams $params, ?UserChannelAccount $account = null): ?Transaction
    {
        $this->throwIfMissing($params, ["amount", "bankCard"]);

        try {
            DB::beginTransaction();

            $data = [
                "from_id" => 0,
                "from_wallet_id" => 0,
                "to_id" => 0,
                "to_channel_account_id" => null,
                "type" => Transaction::TYPE_INTERNAL_TRANSFER,
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

            $transaction = Transaction::create($data);

            DB::commit();
        } catch (RuntimeException $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            DB::rollback();

            return null;
        }

        return $transaction;
    }

    public function paufenTransactionTo(TransactionParams $params, User $merchant, Channel $channel)
    {
        $this->throwIfMissing($params, ["amount", "clientIpv4"]);

        $to = array_merge(
            [
                UserChannelAccount::DETAIL_KEY_REAL_NAME => $params->realName,
                "query" => json_encode(request()->all()),
                "binance_usdt_rate" => $params->binanceUsdtRate,
            ],
            $params->toData
        );
        return DB::transaction(function () use ($params, $merchant, $channel, $to) {
            $transaction = Transaction::create([
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
            ]);

            if ($transaction->channel->note_enable) {
                if ($channel->country == "vn") {
                    $transaction->update([
                        "note" => Str::substr(
                            $transaction->system_order_number,
                            -6
                        ),
                    ]);
                } else {
                    $transaction->update([
                        "note" =>
                        str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT)
                    ]);
                }
            }

            return $transaction;
        });
    }
}
