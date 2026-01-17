<?php

namespace App\Builders;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Transaction as TransactionModel;
use App\Models\FeatureToggle;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;

class Transaction
{
    public function transactions($request)
    {
        $transactions = TransactionModel::orderByDesc("transactions.id");

        $transactions->where("type", TransactionModel::TYPE_PAUFEN_TRANSACTION);

        $transactions->when($request->status, function ($builder, $status) {
            $builder->whereIn("status", $status);
        });

        $transactions->when(
            $request->order_number_or_system_order_number,
            function ($builder, $orderNumberOrSystemOrderNumber) {
                $builder->where(function ($transaction) use (
                    $orderNumberOrSystemOrderNumber
                ) {
                    $transaction
                        ->where("order_number", $orderNumberOrSystemOrderNumber)
                        ->orWhere(
                            "system_order_number",
                            $orderNumberOrSystemOrderNumber
                        );
                });
            }
        );

        $transactions->when($request->order_number, function (
            $builder,
            $orderNumber
        ) {
            $builder->where("order_number", $orderNumber);
        });

        $transactions->when($request->system_order_number, function (
            $builder,
            $orderNumber
        ) {
            $builder->where("system_order_number", $orderNumber);
        });

        $transactions->when($request->provider_name_or_username, function (
            $builder,
            $providerNameOrUsername
        ) {
            if (is_string($providerNameOrUsername)) {
                $providerNameOrUsername = explode(",", $providerNameOrUsername);
            }

            $builder->whereIn("from_id", function ($query) use (
                $providerNameOrUsername
            ) {
                $query
                    ->select("id")
                    ->from("users")
                    ->whereIn("username", $providerNameOrUsername);
            });
        });

        $transactions->when($request->merchant_name_or_username, function (
            $builder,
            $merchantNameOrUsername
        ) {
            if (is_string($merchantNameOrUsername)) {
                $merchantNameOrUsername = explode(",", $merchantNameOrUsername);
            }

            $builder->whereIn("to_id", function ($query) use (
                $merchantNameOrUsername
            ) {
                $query
                    ->select("id")
                    ->from("users")
                    ->whereIn("username", $merchantNameOrUsername);
            });
        });

        $transactions->when($request->merchant_ids, function (
            $builder,
            $merchantIds
        ) {
            $builder->whereIn("to_id", $merchantIds);
        });

        $transactions->when($request->channel_code, function (
            $builder,
            $channelCode
        ) {
            $builder->whereIn("channel_code", $channelCode);
        });

        $transactions->when($request->amount, function ($builder, $amount) {
            if (!Str::contains($amount, ["~"])) {
                $builder->where("floating_amount", $amount);
            } else {
                [$minAmount, $maxAmount] = explode("~", $amount);
                $builder
                    ->where("floating_amount", ">=", $minAmount)
                    ->where("floating_amount", "<=", $maxAmount);
            }
        });

        $transactions->when($request->notify_status, function (
            $builder,
            $notifyStatus
        ) {
            $builder->whereIn("notify_status", $notifyStatus);
        });

        $transactions->when($request->provider_device_name, function (
            $builder,
            $providerDeviceName
        ) {
            $builder->where(
                "from_device_name",
                "like",
                "%$providerDeviceName%"
            );
        });

        $transactions->when(
            $request->provider_channel_account_hash_id,
            function ($builder, $providerChannelAccountHashId) {
                $account = UserChannelAccount::whereIn(
                    "name",
                    $providerChannelAccountHashId
                );
                if ($account) {
                    $builder->whereIn(
                        "_from_channel_account",
                        $account->pluck("account")
                    );
                }
            }
        );

        $transactions->when($request->real_name, function (
            $builder,
            $realName
        ) {
            $builder->where("to_channel_account->real_name", $realName);
        });

        $transactions->when($request->phone_account, function (
            $builder,
            $phone
        ) {
            $builder->where("_search2", $phone);
        });

        $transactions->when($request->thirdchannel_id, function ($builder) use (
            $request
        ) {
            $builder->whereIn(
                "transactions.thirdchannel_id",
                $request->thirdchannel_id
            );
        });

        $transactions->when($request->client_ipv4, function ($builder) use (
            $request
        ) {
            $builder->where("client_ipv4", ip2long($request->client_ipv4));
        });

        $transactions->when($request->account, function ($builder, $account) {
            $builder->where("from_channel_account->account", $account);
        });

        $transactions->when($request->_search1, function ($builder, $search1) {
            $builder->where("_search1", $search1);
        });

        $transactions->when($request->started_at, function (
            $builder,
            $startedAt
        ) use ($request) {
            if ($request->confirmed === "confirmed") {
                $builder->where(
                    "transactions.confirmed_at",
                    ">=",
                    Carbon::make($startedAt)->tz(config("app.timezone"))
                );
            } else {
                $builder->where(
                    "transactions.created_at",
                    ">=",
                    Carbon::make($startedAt)->tz(config("app.timezone"))
                );
            }
        });

        $transactions->when($request->ended_at, function (
            $builder,
            $endedAt
        ) use ($request) {
            if ($request->confirmed === "confirmed") {
                $builder->where(
                    "transactions.confirmed_at",
                    "<=",
                    Carbon::make($endedAt)->tz(config("app.timezone"))
                );
            } else {
                $builder->where(
                    "transactions.created_at",
                    "<=",
                    Carbon::make($endedAt)->tz(config("app.timezone"))
                );
            }
        });

        return $transactions;
    }
    public function withdraws($request)
    {
        $featureToggleRepository = app(FeatureToggleRepository::class);

        $types = collect($request->type)->combine($request->type);

        $paufenWithdrawAvailableForAdminSelected =
            $types->pull(
                TransactionModel::TYPE_VIRTUAL_PAUFEN_WITHDRAW_AVAILABLE_FOR_ADMIN
            ) &&
            $featureToggleRepository->enabled(
                FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT
            );

        $withdraws = TransactionModel::when(
            $paufenWithdrawAvailableForAdminSelected,
            function (Builder $transactions) use (
                $featureToggleRepository,
                $types
            ) {
                $transactions->where(function (Builder $transactions) use (
                    $featureToggleRepository,
                    $types
                ) {
                    $paufenWithdrawTimedOutInSeconds = $featureToggleRepository->valueOf(
                        FeatureToggle::FEATURE_PAUFEN_WITHDRAW_MATCHING_TIMED_OUT
                    );
                    $transactions
                        ->where(function (Builder $transactions) use (
                            $paufenWithdrawTimedOutInSeconds
                        ) {
                            $transactions
                                ->where(
                                    "type",
                                    TransactionModel::TYPE_PAUFEN_WITHDRAW
                                )
                                ->whereNull("to_id")
                                ->where(
                                    "status",
                                    TransactionModel::STATUS_MATCHING
                                )
                                ->whereNull("locked_at")
                                ->where(
                                    "transactions.created_at",
                                    "<=",
                                    now()->subSeconds(
                                        $paufenWithdrawTimedOutInSeconds
                                    )
                                );
                        })
                        ->orWhere(function (Builder $transactions) use (
                            $types
                        ) {
                            $transactions->whereIn("type", $types);
                        });
                });
            },
            function (Builder $transactions) use ($types) {
                $transactions->whereIn("type", $types);
            }
        )->orderByDesc("transactions.id");

        $withdraws->when($request->has("bank_card_q"), function (
            Builder $withdraws
        ) use ($request) {
            $withdraws->where(function (Builder $withdraws) use ($request) {
                $bankCardQ = $request->bank_card_q;

                $withdraws
                    ->where(
                        "from_channel_account->bank_card_holder_name",
                        "like",
                        "%$bankCardQ%"
                    )
                    ->orWhere(
                        "from_channel_account->bank_card_number",
                        $bankCardQ
                    )
                    ->orWhere(
                        "from_channel_account->bank_name",
                        "like",
                        "%$bankCardQ%"
                    );
            });
        });

        $withdraws->when($request->has("bank_card_number"), function (
            Builder $withdraws
        ) use ($request) {
            $withdraws->where(function (Builder $withdraws) use ($request) {
                $withdraws->where("_search2", $request->bank_card_number);
            });
        });

        $withdraws->when(
            $request->order_number_or_system_order_number,
            function ($builder, $orderNumberOrSystemOrderNumber) {
                $builder->where(function ($transaction) use (
                    $orderNumberOrSystemOrderNumber
                ) {
                    $transaction
                        ->where("order_number", $orderNumberOrSystemOrderNumber)
                        ->orWhere(
                            "system_order_number",
                            $orderNumberOrSystemOrderNumber
                        );
                });
            }
        );

        $withdraws->when($request->order_number, function (
            $builder,
            $orderNumber
        ) {
            $builder->where("order_number", $orderNumber);
        });

        $withdraws->when($request->system_order_number, function (
            $builder,
            $orderNumber
        ) {
            $builder->where("system_order_number", $orderNumber);
        });

        $withdraws->when($request->name_or_username, function (
            $builder,
            $nameOrUsername
        ) {
            if (is_string($nameOrUsername)) {
                $nameOrUsername = explode(",", $nameOrUsername);
            }

            $builder->whereIn("from_id", function ($query) use (
                $nameOrUsername
            ) {
                $query
                    ->select("id")
                    ->from("users")
                    ->whereIn("username", $nameOrUsername);
            });
        });

        $withdraws->when($request->merchant_ids, function (
            $builder,
            $merchantIds
        ) {
            $builder->whereIn("from_id", $merchantIds);
        });

        $withdraws->when($request->sub_type, function ($builder, $subType) {
            $builder->where(function ($query) use ($subType) {
                $query->whereIn("sub_type", $subType);
                if (in_array(0, $subType)) {
                    $query->orWhereNull("sub_type");
                }
            });
        });

        $withdraws->when($request->status, function ($builder, $status) {
            $builder->whereIn("status", $status);
        });

        $withdraws->when($request->notify_status, function (
            $builder,
            $notifyStatus
        ) {
            $builder->whereIn("notify_status", $notifyStatus);
        });

        $withdraws->when($request->amount, function ($builder, $amount) {
            if (!Str::contains($amount, ["~"])) {
                $builder->where("floating_amount", $amount);
            } else {
                [$minAmount, $maxAmount] = explode("~", $amount);
                $builder
                    ->where("floating_amount", ">=", $minAmount)
                    ->where("floating_amount", "<=", $maxAmount);
            }
        });

        $withdraws->when($request->operator_name_or_username, function (
            $builder,
            $operatorNameOrUsername
        ) {
            $builder->whereIn("locked_by_id", function ($query) use (
                $operatorNameOrUsername
            ) {
                $query
                    ->select("id")
                    ->from("users")
                    ->where("name", "like", "%$operatorNameOrUsername%")
                    ->orWhere("username", $operatorNameOrUsername);
            });
        });

        $withdraws->when($request->thirdchannel_id, function ($builder, $id) {
            $builder->whereIn("transactions.thirdchannel_id", $id);
        });

        $withdraws->when($request->account, function ($builder, $account) {
            $builder->where("to_channel_account->account", $account);
        });

        $withdraws->when($request->_search1, function ($builder, $search1) {
            $builder->where("_search1", $search1);
        });

        $withdraws->when($request->started_at, function (
            $builder,
            $startedAt
        ) use ($request) {
            if ($request->confirmed === "confirmed") {
                $builder->where(
                    "transactions.confirmed_at",
                    ">=",
                    Carbon::make($startedAt)->tz(config("app.timezone"))
                );
            } else {
                $builder->where(
                    "transactions.created_at",
                    ">=",
                    Carbon::make($startedAt)->tz(config("app.timezone"))
                );
            }
        });

        $withdraws->when($request->ended_at, function ($builder, $endedAt) use (
            $request
        ) {
            if ($request->confirmed === "confirmed") {
                $builder->where(
                    "transactions.confirmed_at",
                    "<=",
                    Carbon::make($endedAt)->tz(config("app.timezone"))
                );
            } else {
                $builder->where(
                    "transactions.created_at",
                    "<=",
                    Carbon::make($endedAt)->tz(config("app.timezone"))
                );
            }
        });

        return $withdraws;
    }

    public function deposits($request)
    {
        $deposits = TransactionModel::whereIn("type", [
            TransactionModel::TYPE_PAUFEN_WITHDRAW,
            TransactionModel::TYPE_NORMAL_DEPOSIT,
        ])
            ->whereNotNull("to_id")
            ->orderByDesc("transactions.id")
            ->with(
                "from",
                "to",
                "to.rootParents",
                "to.wallet",
                "certificateFiles",
                "transactionNotes.user",
                "lockedBy",
                "toChannelAccount",
                "toChannelAccount.device"
            );

        $deposits->when($request->started_at, function ($builder, $startedAt) {
            $builder->where(
                "transactions.created_at",
                ">=",
                Carbon::make($startedAt)->tz(config("app.timezone"))
            );
        });

        $deposits->when($request->ended_at, function ($builder, $endedAt) {
            $builder->where(
                "transactions.created_at",
                "<=",
                Carbon::make($endedAt)->tz(config("app.timezone"))
            );
        });

        $deposits->when(
            $request->order_number_or_system_order_number,
            function ($builder, $orderNumberOrSystemOrderNumber) {
                $builder->where(function ($transaction) use (
                    $orderNumberOrSystemOrderNumber
                ) {
                    $transaction
                        ->where(
                            "order_number",
                            "like",
                            "%$orderNumberOrSystemOrderNumber%"
                        )
                        ->orWhere(
                            "system_order_number",
                            "like",
                            "%$orderNumberOrSystemOrderNumber%"
                        );
                });
            }
        );

        $deposits->when($request->system_order_number, function (
            $builder,
            $systemOrderNumber
        ) {
            $builder->where(
                "system_order_number",
                "like",
                "%$systemOrderNumber%"
            );
        });

        $deposits->when($request->provider_name_or_username, function (
            $builder,
            $providerNameOrUsername
        ) {
            if (is_string($providerNameOrUsername)) {
                $providerNameOrUsername = explode(",", $providerNameOrUsername);
            }

            $builder->whereIn("to_id", function ($query) use (
                $providerNameOrUsername
            ) {
                $query
                    ->select("id")
                    ->from("users")
                    ->whereIn("username", $providerNameOrUsername);
            });
        });

        $deposits->when($request->merchant_name_or_username, function (
            $builder,
            $merchantNameOrUsername
        ) {
            if (is_string($merchantNameOrUsername)) {
                $merchantNameOrUsername = explode(",", $merchantNameOrUsername);
            }

            $builder->whereIn("from_id", function ($query) use (
                $merchantNameOrUsername
            ) {
                $query
                    ->select("id")
                    ->from("users")
                    ->whereIn("username", $merchantNameOrUsername);
            });
        });

        $deposits->when($request->status, function ($builder, $status) {
            $builder->whereIn("status", $status);
        });

        $deposits->when($request->type, function ($builder, $type) {
            $builder->where("type", $type);
        });

        $deposits->when($request->has("bank_card_q"), function (
            Builder $deposits
        ) use ($request) {
            $deposits->where(function (Builder $deposits) use ($request) {
                $bankCardQ = $request->bank_card_q;

                $deposits
                    ->where(
                        "from_channel_account->bank_card_holder_name",
                        "like",
                        "%$bankCardQ%"
                    )
                    ->orWhere(
                        "from_channel_account->bank_card_number",
                        $bankCardQ
                    )
                    ->orWhere(
                        "from_channel_account->bank_name",
                        "like",
                        "%$bankCardQ%"
                    );
            });
        });

        $deposits->when($request->amount, function ($builder, $amount) {
            if (!Str::contains($amount, ["~"])) {
                $builder->where("floating_amount", $amount);
            } else {
                [$minAmount, $maxAmount] = explode("~", $amount);
                $builder
                    ->where("floating_amount", ">=", $minAmount)
                    ->where("floating_amount", "<=", $maxAmount);
            }
        });

        $deposits->when($request->operator_name_or_username, function (
            $builder,
            $operatorNameOrUsername
        ) {
            $builder->whereIn("locked_by_id", function ($query) use (
                $operatorNameOrUsername
            ) {
                $query
                    ->select("id")
                    ->from("users")
                    ->where("name", "like", "%$operatorNameOrUsername%")
                    ->orWhere("username", $operatorNameOrUsername);
            });
        });

        return $deposits;
    }

    public function internalTransfer($request)
    {
        $transactions = TransactionModel::orderByDesc("transactions.id");

        $transactions->where("type", TransactionModel::TYPE_INTERNAL_TRANSFER);

        $transactions->when($request->status, function ($builder, $status) {
            $builder->whereIn("status", $status);
        });

        $transactions->when($request->system_order_number, function (
            $builder,
            $orderNumber
        ) {
            $builder->where("system_order_number", $orderNumber);
        });

        $transactions->when($request->has("bank_card_number"), function (
            Builder $withdraws
        ) use ($request) {
            $withdraws->where(function (Builder $withdraws) use ($request) {
                $withdraws->where("_search2", $request->bank_card_number);
            });
        });

        $transactions->when($request->account, function ($builder, $account) {
            $builder->where("_from_channel_account", $account);
        });

        $transactions->when($request->_search1, function ($builder, $search1) {
            $builder->where("_search1", $search1);
        });

        $transactions->when($request->started_at, function (
            $builder,
            $startedAt
        ) use ($request) {
            if ($request->confirmed === "confirmed") {
                $builder->where(
                    "transactions.confirmed_at",
                    ">=",
                    Carbon::make($startedAt)->tz(config("app.timezone"))
                );
            } else {
                $builder->where(
                    "transactions.created_at",
                    ">=",
                    Carbon::make($startedAt)->tz(config("app.timezone"))
                );
            }
        });

        $transactions->when($request->ended_at, function (
            $builder,
            $endedAt
        ) use ($request) {
            if ($request->confirmed === "confirmed") {
                $builder->where(
                    "transactions.confirmed_at",
                    "<=",
                    Carbon::make($endedAt)->tz(config("app.timezone"))
                );
            } else {
                $builder->where(
                    "transactions.created_at",
                    "<=",
                    Carbon::make($endedAt)->tz(config("app.timezone"))
                );
            }
        });

        return $transactions;
    }
}
