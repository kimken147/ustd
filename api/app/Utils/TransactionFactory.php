<?php

namespace App\Utils;

use App\DTOs\TransactionParams;
use App\Exceptions\RaceConditionException;
use App\Models\Channel;
use App\Models\DevicePayingTransaction;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionNote;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionFeeService;
use App\Utils\BCMathUtil;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class TransactionFactory
{
    private $bcMath;
    private $userChannelAccountUtil;
    private $featureToggleRepository;
    private $transactionFeeService;

    public function __construct(
        BCMathUtil $bcMath,
        UserChannelAccountUtil $userChannelAccountUtil,
        FeatureToggleRepository $featureToggleRepository,
        TransactionFeeService $transactionFeeService
    ) {
        $this->bcMath = $bcMath;
        $this->userChannelAccountUtil = $userChannelAccountUtil;
        $this->featureToggleRepository = $featureToggleRepository;
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

    public function createThirdchannel(
        TransactionParams $params,
        User $user,
    ) {
        $this->throwIfMissing($params, ["amount", "bankCard"]);
        $transactionData = [
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
        ];
        return Transaction::create($transactionData);
    }

    public function changeToPaufenWithdraw(Transaction $transaction, User $user, $agency = true, ?Transaction $parent = null)
    {
        $transaction->type = Transaction::TYPE_PAUFEN_WITHDRAW;
        $transaction->to_id = null;
        $transaction->status = !$parent && $user->withdraw_review_enable
            ? Transaction::STATUS_PENDING_REVIEW
            : Transaction::STATUS_MATCHING;
        $transaction->save();
    }

    public function changeToNormalWithdraw(Transaction $transaction, User $user, $agency = true, ?Transaction $parent = null)
    {
        $transaction->type = Transaction::TYPE_NORMAL_WITHDRAW;
        $transaction->status = !$parent && $user->withdraw_review_enable
            ? Transaction::STATUS_PENDING_REVIEW
            : Transaction::STATUS_PAYING;
        $transaction->save();
    }

    public function assignThirdChannel(Transaction $transaction, $thirdChannelId, User $user, $agency = true)
    {
        if (!$transaction->thirdchannel_id) {
            $updated = $transaction->update([
                'thirdchannel_id' => $thirdChannelId
            ]);
            if ($updated) {
                $transaction->thirdchannel_id = $thirdChannelId;
                $this->transactionFeeService->createWithdrawFees($transaction, $user, $agency);
            } else {
                return false;
            }
            return $transaction;
        }

        return false;
    }

    public function assignThirdChannelV2(Transaction $transaction, $thirdChannelId)
    {
        if (!$transaction->thirdchannel_id) {
            $updated = $transaction->update([
                'thirdchannel_id' => $thirdChannelId
            ]);
            if ($updated) {
                $transaction->thirdchannel_id = $thirdChannelId;
            } else {
                return false;
            }
            return $transaction;
        }
        return false;
    }

    public function changeToThirdChannelPending(Transaction $transaction)
    {
        $updated = $transaction->update([
            'status' => Transaction::STATUS_THIRD_PAYING
        ]);
        if ($updated) {
            $transaction->status = Transaction::STATUS_THIRD_PAYING;
        }
        return $transaction;
    }

    public function paufenDepositTo(
        User $provider,
        Transaction $transaction,
        ?Transaction $parent = null
    ) {
        return DB::transaction(function () use (
            $provider,
            $transaction,
            $parent
        ) {
            $success =
                Transaction::where("id", $transaction->getKey())
                ->whereNull("to_id")
                ->whereNull("locked_at")
                ->where("status", Transaction::STATUS_MATCHING)
                ->where("type", Transaction::TYPE_PAUFEN_WITHDRAW)
                ->update([
                    "to_id" => $provider->getKey(),
                    "to_wallet_id" => $provider->wallet->getKey(),
                    "to_account_mode" => $provider->account_mode,
                    "status" => Transaction::STATUS_PAYING,
                    "matched_at" => now(),
                ]) === 1;

            throw_if(
                !$success &&
                    $transaction->refresh()->status !==
                    Transaction::STATUS_MATCHING,
                new RaceConditionException()
            );

            throw_if(!$success, new RuntimeException("Unknown"));

            $this->transactionFeeService->createDepositFees($transaction, $provider, false, $parent);

            return $transaction;
        });
    }

    public function paufenDepositToAccount(
        UserChannelAccount $account,
        Transaction $transaction,
        ?Transaction $parent = null
    ) {
        return DB::transaction(function () use (
            $account,
            $transaction,
            $parent
        ) {
            $featureToggleRepository = app(FeatureToggleRepository::class);

            throw_if(
                $featureToggleRepository->enabled(
                    FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE,
                    false
                ) && $account->balance < $transaction->floating_amount,
                new InsufficientAvailableBalance()
            );

            $provider = $account->user;
            $fromChannelAccount = $transaction->from_channel_account;

            $fromChannelAccount["extra_withdraw_fee"] = 0;
            $success =
                Transaction::where("id", $transaction->getKey())
                ->whereNull("to_id")
                ->whereNull("locked_at")
                ->where("status", Transaction::STATUS_MATCHING)
                ->where("type", Transaction::TYPE_PAUFEN_WITHDRAW)
                ->update([
                    "to_id" => $provider->getKey(),
                    "to_wallet_id" => $provider->wallet->getKey(),
                    "to_channel_account_id" => $account->id,
                    "to_channel_account" => array_merge($account->detail, [
                        "channel_code" => $account->channel_code,
                    ]),
                    "from_channel_account" => $fromChannelAccount,
                    "to_account_mode" => $provider->account_mode,
                    "status" => Transaction::STATUS_PAYING,
                    "matched_at" => now(),
                ]) === 1;

            throw_if(
                !$success &&
                    $transaction->refresh()->status !==
                    Transaction::STATUS_MATCHING,
                new RaceConditionException()
            );

            throw_if(!$success, new RuntimeException("Unknown"));

            $this->transactionFeeService->createDepositFees($transaction, $provider, false, $parent);

            $amount = $this->bcMath->add(
                $transaction->floating_amount,
                data_get($fromChannelAccount, "extra_withdraw_fee", 0)
            );
            $this->userChannelAccountUtil->updateTotal(
                $account->id,
                $amount,
                true
            );

            return $transaction;
        });
    }

    public function paufenTransactionFrom(
        UserChannelAccount $providerUserChannelAccount,
        Transaction $transaction
    ) {
        $transaction = DB::transaction(function () use (
            $providerUserChannelAccount,
            $transaction
        ) {
            $bcMath = app(BCMathUtil::class);

            $defaultDailyLimit = 0;
            $dailyLimitEnabled = $this->featureToggleRepository->enabled(
                FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT
            );
            if ($dailyLimitEnabled) {
                $defaultDailyLimit = $this->featureToggleRepository->valueOf(
                    FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT
                );
            }

            $defaultMonthlyLimit = 0;
            $monthlyLimitEnabled = $this->featureToggleRepository->enabled(
                FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT
            );
            if ($monthlyLimitEnabled) {
                $defaultMonthlyLimit = $this->featureToggleRepository->valueOf(
                    FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT
                );
            }

            $account = UserChannelAccount::find(
                $providerUserChannelAccount->id
            );
            $amount = Transaction::where(
                "from_channel_account_id",
                $account->id
            )
                ->where("status", Transaction::STATUS_PAYING)
                ->where("created_at", ">=", now()->subMinutes(15))
                ->sum("amount");

            if (
                $account->balance_limit != 0 &&
                $bcMath->sum([
                    $account->balance,
                    $amount,
                    $transaction->amount,
                ]) > $account->balance_limit
            ) {
                throw new RuntimeException("{$account->account}餘度不足");
            }

            $dailyLimit =
                $dailyLimitEnabled && $account->daily_status
                ? $account->daily_limit
                : $defaultDailyLimit;
            if (
                $dailyLimit &&
                $bcMath->sum([
                    $account->daily_total,
                    $amount,
                    $transaction->amount,
                ]) > $dailyLimit
            ) {
                throw new RuntimeException("{$account->account}日收餘度不足");
            }

            $monthlyLimit =
                $monthlyLimitEnabled && $account->monthly_status
                ? $account->monthly_limit
                : $defaultMonthlyLimit;
            if (
                $monthlyLimit &&
                $bcMath->sum([
                    $account->monthly_total,
                    $amount,
                    $transaction->amount,
                ]) > $monthlyLimit
            ) {
                throw new RuntimeException("{$account->account}月收餘度不足");
            }

            $updatedRow = Transaction::where([
                ["id", $transaction->getKey()],
                ["status", Transaction::STATUS_MATCHING],
            ])->update([
                "from_id" => $providerUserChannelAccount->user_id,
                "from_wallet_id" => $providerUserChannelAccount->wallet_id,
                "from_channel_account_id" => $providerUserChannelAccount->getKey(),
                "from_account_mode" =>
                $providerUserChannelAccount->user->account_mode,
                "from_channel_account" => array_merge(
                    $providerUserChannelAccount->detail,
                    ["account" => $providerUserChannelAccount->account]
                ),
                "status" => Transaction::STATUS_PAYING,
                "matched_at" => now(),
                "from_channel_account_hash_id" =>
                $providerUserChannelAccount->name,
                "from_device_name" => optional(
                    $providerUserChannelAccount->device
                )->name,
            ]);

            if ($updatedRow !== 1) {
                throw new RuntimeException("Conflict");
            }

            return $transaction->refresh();
        });

        if (
            !$this->featureToggleRepository->enabled(
                FeatureToggle::CANCEL_PAUFEN_MECHANISM
            )
        ) {
            DevicePayingTransaction::create([
                "device_id" => $providerUserChannelAccount->device_id,
                "user_channel_account_id" => $providerUserChannelAccount->getKey(),
                "amount" => $transaction->floating_amount,
                "transaction_id" => $transaction->getKey(),
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }

        $this->transactionFeeService->createPaufenTransactionFees(
            $transaction,
            $providerUserChannelAccount->channelAmount->channelGroup
        );

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
