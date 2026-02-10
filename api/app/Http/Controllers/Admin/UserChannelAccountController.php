<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserChannelAccountCollection;
use App\Models\FeatureToggle;
use App\Models\Permission;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Models\ChannelAmount;
use App\Models\TransactionGroup;
use App\Models\Device;
use App\Models\Bank;
use App\Models\UserChannelAccountAudit;
use App\Utils\AmountDisplayTransformer;
use App\Repository\FeatureToggleRepository;
use App\Services\UserChannelAccount\UserChannelAccountService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use App\Http\Resources\UserChannelAccountAuditCollection;
use App\Builders\UserChannelAccount as UserChannelAccountBuilder;
use App\Jobs\SyncGcashAccount;
use App\Jobs\SyncMayaAccountJob;
use App\Models\Channel;
use App\Models\MemberDevice;
use Illuminate\Support\Facades\Redis;

class UserChannelAccountController extends Controller
{
    public function __construct(
        private readonly UserChannelAccountService $userChannelAccountService
    ) {
        $this->middleware([
            "permission:" . Permission::ADMIN_UPDATE_USER_CHANNEL_ACCOUNT,
        ])->only("update");
        $this->middleware([
            "permission:" . Permission::ADMIN_DESTROY_USER_CHANNEL_ACCOUNT,
        ])->only("destroy");
    }

    public function destroy(UserChannelAccount $userChannelAccount)
    {
        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER,
            Response::HTTP_NOT_FOUND
        );

        DB::transaction(function () use ($userChannelAccount) {
            $userChannelAccount->update([
                "status" => UserChannelAccount::STATUS_DISABLE,
            ]);

            abort_if(
                !$userChannelAccount->delete(),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );

            DB::table("transaction_group_user_channel_account")
                ->where("user_channel_account_id", $userChannelAccount->id)
                ->delete();
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function index(
        Request $request,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $this->validate($request, [
            "name_or_username" => "nullable|string",
            "agent_name_or_username" => "nullable|string",
            "channel_code" => "array",
            "status" => "array",
            "device_name" => "nullable|string",
            "account_name" => "nullable|string",
            "hash_id" => "nullable|array",
            "provider_id" => "nullable|numeric",
            "channel_group" => "nullable|numeric",
        ]);

        $builder = new UserChannelAccountBuilder();
        $userChannelAccounts = $builder->query($request)->with("user");

        $totalBalance = (clone $userChannelAccounts)->first([
            DB::raw("SUM(balance) AS total_balance"),
        ]);

        $dailyLimitId = FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT;
        $dailyLimitEnabled = $featureToggleRepository->enabled($dailyLimitId);
        $dailyLimitvalue = $featureToggleRepository->valueOf($dailyLimitId);

        $monthlyLimitId = FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT;
        $monthlyLimitEnabled = $featureToggleRepository->enabled(
            $monthlyLimitId
        );
        $monthlyLimitvalue = $featureToggleRepository->valueOf($monthlyLimitId);

        $perPage = $request->input("per_page", 20);
        $data = !empty($request->no_paginate)
            ? $userChannelAccounts->get()
            : $userChannelAccounts
            ->paginate($perPage)
            ->appends($request->query->all());
        $data->transform(function ($value) use (
            $dailyLimitEnabled,
            $dailyLimitvalue,
            $monthlyLimitEnabled,
            $monthlyLimitvalue
        ) {
            $value->user_channel_account_daily_limit_enabled = $dailyLimitEnabled;
            $value->user_channel_account_daily_limit_value = $dailyLimitvalue;
            $value->user_channel_account_monthly_limit_enabled = $monthlyLimitEnabled;
            $value->user_channel_account_monthly_limit_value = $monthlyLimitvalue;
            return $value;
        });

        return UserChannelAccountCollection::make($data)->additional([
            "meta" => [
                "late_night_bank_limit_feature_enabled" => $featureToggleRepository->enabled(
                    FeatureToggle::LATE_NIGHT_BANK_LIMIT
                ),
                "user_channel_account_daily_limit_enabled" => $dailyLimitEnabled,
                "user_channel_account_daily_limit_value" => $dailyLimitvalue,
                "user_channel_account_monthly_limit_enabled" => $monthlyLimitEnabled,
                "user_channel_account_monthly_limit_value" => $monthlyLimitvalue,
                "record_user_channeL_account_balance" => $featureToggleRepository->enabled(
                    FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE
                ),
                "total_balance" => data_get(
                    $totalBalance,
                    "total_balance",
                    "0.00"
                ),
            ],
        ]);
    }

    public function show(
        FeatureToggleRepository $featureToggleRepository,
        $userChannelAccountId
    ) {
        $userChannelAccount = UserChannelAccount::withTrashed()->findOrFail(
            $userChannelAccountId
        );

        abort_if(
            $userChannelAccount->user->role !== User::ROLE_PROVIDER,
            Response::HTTP_NOT_FOUND
        );

        $userChannelAccount->user_channel_account_daily_limit_enabled = $featureToggleRepository->enabled(
            FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT
        );
        $userChannelAccount->user_channel_account_daily_limit_value = $featureToggleRepository->valueOf(
            FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT
        );

        $userChannelAccount->user_channel_account_monthly_limit_enabled = $featureToggleRepository->enabled(
            FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT
        );
        $userChannelAccount->user_channel_account_monthly_limit_value = $featureToggleRepository->valueOf(
            FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT
        );
        $userChannelAccount->record_user_channeL_account_balance = $featureToggleRepository->enabled(
            FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE
        );

        return \App\Http\Resources\UserChannelAccount::make(
            $userChannelAccount->load("user.parent", "channelAmount.channel")
        );
    }

    public function update(Request $request)
    {
        abort_if(!$request->id && !$request->all, Response::HTTP_NOT_FOUND);

        $this->validate($request, [
            "status" => [
                Rule::in(
                    UserChannelAccount::STATUS_ENABLE,
                    UserChannelAccount::STATUS_DISABLE,
                    UserChannelAccount::STATUS_ONLINE
                ),
                "nullable",
            ],
            "daily_status" => ["boolean", "nullable"],
            "daily_limit" => ["numeric", "nullable"],
            "daily_total" => ["numeric", "nullable"],
            "daily_limit_null" => ["boolean", "nullable"],
            "monthly_status" => ["boolean", "nullable"],
            "monthly_limit" => ["numeric", "nullable"],
            "monthly_total" => ["numeric", "nullable"],
            "monthly_limit_null" => ["boolean", "nullable"],
            "balance" => ["numeric", "nullable"],
            "balance_limit" => ["numeric", "nullable"],
            'single_min_limit' => ['numeric', 'nullable'],
            'single_max_limit' => ['numeric', 'nullable'],
            'withdraw_single_min_limit' => ['numeric', 'nullable'],
            'withdraw_single_max_limit' => ['numeric', 'nullable'],
        ]);

        // 更新單個收款帳號
        if (!$request->has("all")) {
            $userChannelAccount = UserChannelAccount::where(
                "id",
                $request->id
            )->first();

            abort_if(
                $userChannelAccount->user->role !== User::ROLE_PROVIDER,
                Response::HTTP_NOT_FOUND
            );

            if (
                $request->status === UserChannelAccount::STATUS_ONLINE &&
                $userChannelAccount->type == UserChannelAccount::TYPE_DEPOSIT
            ) {
                // 收款帳號才檢查
                $channelGroupId =
                    $userChannelAccount->channelAmount->channel_group_id;
                $userChannel = UserChannel::where(
                    "user_id",
                    $userChannelAccount->user_id
                )
                    ->where("channel_group_id", $channelGroupId)
                    ->firstOrFail();
                abort_if(
                    $userChannel->status !== UserChannel::STATUS_ENABLED,
                    Response::HTTP_BAD_REQUEST,
                    "请先开启码商通道"
                );
            }

            foreach ([
                "name",
                "status",
                "type",
                "is_auto",
                "auto_sync",
                "account",
                "note",
                "daily_status",
                "daily_limit",
                "daily_total",
                "withdraw_daily_limit",
                "withdraw_daily_total",
                "monthly_status",
                "monthly_limit",
                "monthly_total",
                "withdraw_monthly_limit",
                "withdraw_monthly_total",
                "balance_limit",
                'single_min_limit',
                'single_max_limit',
                'withdraw_single_min_limit',
                'withdraw_single_max_limit',
            ]
                as $key => $value) {
                if ($request->has($value)) {
                    $updateData[$value] = $request->{$value};
                }
            }

            $detail = $userChannelAccount->detail;
            foreach (["account", "mpin", "new_mpin", "otp", "pin", "pwd"]
                as $key => $value) {
                if ($request->has($value)) {
                    $detail[$value] = $request->{$value};
                }
            }
            if (
                $request->has("status") &&
                $request->status != UserChannelAccount::STATUS_ONLINE
            ) {
                $updateData["auto_sync"] = false;
            }
            if (
                $request->has("daily_limit_null") &&
                $request->daily_limit_null
            ) {
                $updateData["daily_limit"] = null;
            }

            if (
                $request->has("monthly_limit_null") &&
                $request->monthly_limit_null
            ) {
                $updateData["monthly_limit"] = null;
            }

            if (
                $request->has("daily_withdraw_limit_null") &&
                $request->daily_withdraw_limit_null
            ) {
                $updateData["withdraw_daily_limit"] = null;
            }

            if (
                $request->has("monthly_withdraw_limit_null") &&
                $request->monthly_withdraw_limit_null
            ) {
                $updateData["withdraw_monthly_limit"] = null;
            }

            # 批量更新-單筆限額
            if ($request->has('all_single_limit')) {
                $updateData['single_min_limit'] = $request->single_min_limit;
                $updateData['single_max_limit'] = $request->single_max_limit;
                $updateData['withdraw_single_min_limit'] = $request->withdraw_single_min_limit;
                $updateData['withdraw_single_max_limit'] = $request->withdraw_single_max_limit;
            }

            # 更新單筆限額-允許無限制
            if ($request->has('allow_unlimited') && $request->has('allow_unlimited') === true) {
                if (!$request->has('single_min_limit')) {
                    $updateData['single_min_limit'] = NULL;
                }
                if (!$request->has('single_max_limit')) {
                    $updateData['single_max_limit'] = NULL;
                }
                if (!$request->has('withdraw_single_min_limit')) {
                    $updateData['withdraw_single_min_limit'] = NULL;
                }
                if (!$request->has('withdraw_single_max_limit')) {
                    $updateData['withdraw_single_max_limit'] = NULL;
                }
            }


            if ($request->has("provider_id")) {
                $provider = User::find($request->provider_id);

                abort_if(!$provider, Response::HTTP_NOT_FOUND, "帐号不存在");
                $updateData["user_id"] = $provider->id;
                $updateData["wallet_id"] = $provider->wallet->id;
                $updateData["device_id"] = $provider->devices->first()->id;
            }

            if ($request->has("device_name")) {
                $provider = User::find($request->user_id)->getKey();
                $device = [
                    "user_id" => $provider,
                    "name" => $request->device_name,
                ];
                Device::insertIgnore($device);
                $device = Device::where($device)->first();
                $updateData["device_id"] = $device->id;
            }

            $updateData["detail"] = $detail;

            $userChannelAccount->update($updateData);

            if ($request->has("balance")) {
                $action = $request->input("action", "modify");
                $note = $request->input("balance_note", "");
                $userChannelAccount->updateBalanceByUser(
                    $request->balance,
                    $action,
                    auth()->user(),
                    $note
                );
            }

            if ($request->has("reset_device")) {
                MemberDevice::where(
                    "device",
                    $userChannelAccount->account
                )->delete();
            }

            if ($request->has("provider_id")) {
                DB::transaction(function () use (
                    $request,
                    $userChannelAccount
                ) {
                    // 刪除原本 碼商/群組 的代收付專線
                    $userChannelAccount->transactionGroups()->detach();

                    // 新增卡到 新碼商/群組的代收付專線
                    $groups = TransactionGroup::where(
                        "worker_id",
                        $request->provider_id
                    )->get();
                    foreach ($groups as $group) {
                        $group
                            ->userChannelAccounts()
                            ->attach($userChannelAccount);
                    }
                });
            }
        } else {
            // 沒帶 ID，更新所有出款帳號
            $userChannelAccounts = UserChannelAccount::all();

            foreach ($userChannelAccounts as $userChannelAccount) {
                $userChannelAccount->update(["status" => $request->status]);
            }
        }
        return \App\Http\Resources\UserChannelAccount::make(
            $userChannelAccount->load("user.parent", "channelAmount.channel")
        );
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            "provider" => "required",
            "channel_amount_id" => "required|int",
        ]);

        $provider = User::where("id", $request->provider)->first();

        abort_if(
            !$provider,
            Response::HTTP_BAD_REQUEST,
            $request->provider . " 查无此码商"
        );

        $data = array_merge(
            $request->only([
                'channel_amount_id', 'account', 'bank_card_number', 'bank_name',
                'device_name', 'status', 'type', 'name', 'note', 'balance',
                'balance_limit', 'is_auto', 'daily_limit', 'withdraw_daily_limit',
                'monthly_limit', 'withdraw_monthly_limit', 'single_min_limit',
                'single_max_limit', 'withdraw_single_min_limit', 'withdraw_single_max_limit',
                'sync_after_create',
            ]),
            [
                'detail' => $request->only(
                    'account', 'bank_name', 'bank_card_number', 'bank_card_holder_name',
                    'bank_card_branch', 'mpin', 'new_mpin', 'mobile', 'receiver_name',
                    'pin', 'otp', 'pwd', 'sync_after_create'
                ),
                'qr_code_file' => $request->file('qr_code'),
            ]
        );

        $userChannelAccount = $this->userChannelAccountService->createAccount($data, $provider);

        Artisan::call("paufen:disable-time-limit-user-channel-account", [
            "user_channel_account" => $userChannelAccount,
        ]);

        return \App\Http\Resources\UserChannelAccount::make(
            $userChannelAccount
        );
    }

    public function massiveStore(Request $request)
    {
        $this->validate($request, [
            "accounts" => "required|array",
        ]);

        $channelAmounts = [];
        foreach ($request->accounts as $account) {
            $channelAmount = data_get(
                $channelAmounts,
                $account["channel_amount_id"]
            );
            if (!$channelAmount) {
                $channelAmount = ChannelAmount::find(
                    $account["channel_amount_id"]
                );
                data_set(
                    $channelAmounts,
                    $account["channel_amount_id"],
                    $channelAmount
                );
            }

            $isExists = UserChannelAccount::where(
                "channel_code",
                $channelAmount->channel_code
            )
                ->where("account", $account["account"])
                ->exists();
            abort_if(
                $isExists,
                Response::HTTP_BAD_REQUEST,
                $account["account"] . " 已存在"
            );
        }

        DB::beginTransaction();
        try {
            $providers = [];
            $banks = [];
            foreach ($request->accounts as $account) {
                $provider = data_get($providers, $account["provider"]);
                if (!$provider) {
                    $provider = User::with("wallet", "devices")->find(
                        $account["provider"]
                    );
                    data_set($providers, $account["provider"], $provider);
                }

                $bank = data_get($banks, $account["bank_name"]);
                if (!$bank) {
                    $bank = Bank::firstWhere("name", $account["bank_name"]);
                    data_set($banks, $account["bank_name"], $bank);
                }

                $data = [
                    'channel_amount_id' => $account['channel_amount_id'],
                    'device'            => $provider->devices->first(),
                    'wallet'            => $provider->wallet,
                    'bank_id'           => optional($bank)->id ?? 0,
                    'account'           => $account['account'],
                    'detail'            => Arr::only($account, [
                        'account', 'bank_name', 'bank_card_number', 'bank_card_holder_name',
                        'bank_card_branch', 'mpin', 'new_mpin', 'mobile', 'receiver_name',
                        'pin', 'otp', 'pwd', 'sync_after_create',
                    ]),
                    'status'                 => data_get($account, 'status', UserChannelAccount::STATUS_DISABLE),
                    'sync_after_create'      => $account['sync_after_create'] ?? null,
                    'type'                   => data_get($account, 'type', UserChannelAccount::TYPE_DEPOSIT_WITHDRAW),
                    'name'                   => $account['name'] ?? null,
                    'note'                   => data_get($account, 'note', ''),
                    'balance'                => data_get($account, 'balance', 0) ?? 0,
                    'balance_limit'          => data_get($account, 'balance_limit'),
                    'is_auto'                => data_get($account, 'is_auto', false),
                    'daily_limit'            => data_get($account, 'daily_limit'),
                    'withdraw_daily_limit'   => data_get($account, 'withdraw_daily_limit'),
                    'monthly_limit'          => data_get($account, 'monthly_limit'),
                    'withdraw_monthly_limit' => data_get($account, 'withdraw_monthly_limit'),
                ];

                $this->userChannelAccountService->createAccountInTransaction($data, $provider);
            }

            DB::commit();
        } catch (exception $e) {
            DB::rollBack();
            abort(Response::HTTP_BAD_REQUEST, "新增失败");
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function batchUpdate(Request $request)
    {
        abort_if(!$request->accounts, Response::HTTP_NOT_FOUND);

        $accounts = UserChannelAccount::whereIn(
            "name",
            explode(",", $request->accounts)
        )->get();
        $notExists = [];

        foreach ($accounts as $account) {
            try {
                DB::beginTransaction();

                $account = UserChannelAccount::find($account->id);

                if (!$account) {
                    $notExists[] = $account;
                    DB::commit();
                    continue;
                }

                $updateData = $request->only(
                    "status",
                    "type",
                    "is_auto",
                    "auto_sync",
                    "balance_limit",
                    "daily_limit",
                    "monthly_limit",
                    "withdraw_daily_limit",
                    "withdraw_monthly_limit"
                );

                if ($request->has("provider_id")) {
                    $provider = User::find($request->provider_id);

                    abort_if(
                        !$provider,
                        Response::HTTP_NOT_FOUND,
                        "帐号不存在"
                    );
                    $updateData["user_id"] = $provider->id;
                    $updateData["wallet_id"] = $provider->wallet->id;
                    $updateData["device_id"] = $provider->devices->first()->id;

                    // 刪除原本 碼商/群組 的代收付專線
                    $account->transactionGroups()->detach();
                    // 新增卡到 新碼商/群組的代收付專線
                    $groups = TransactionGroup::where(
                        "worker_id",
                        $request->provider_id
                    )->get();
                    foreach ($groups as $group) {
                        $group->userChannelAccounts()->attach($account);
                    }
                }

                $account->update($updateData);

                if ($request->has("reset_device")) {
                    MemberDevice::where("device", $account->account)->delete();
                }

                DB::commit();
            } catch (\Exception $e) {
                \Log::error(__METHOD__, $e);
                DB::rollBack();
            }
        }

        $message = "";
        if ($notExists) {
            $message = "帳號 " . implode(",", $notExists) . " 不存在";
        }
        return response()->json(compact("message"));
    }

    public function audits(
        Request $request,
        UserChannelAccount $userChannelAccount
    ) {
        $startedAt = $request->started_at
            ? Carbon::make($request->started_at)->tz(config("app.timezone"))
            : today();
        $endedAt = $request->ended_at
            ? Carbon::make($request->ended_at)->tz(config("app.timezone"))
            : now();

        $audits = UserChannelAccountAudit::with(
            "updateByUser",
            "updateByTransaction"
        )
            ->where("user_channel_account_id", $userChannelAccount->id)
            ->whereBetween("created_at", [$startedAt, $endedAt])
            ->orderByDesc("id")
            ->paginate(20);

        return UserChannelAccountAuditCollection::make($audits);
    }

    public function sync(Request $request)
    {
        $builder = new UserChannelAccountBuilder();
        $userChannelAccounts = $builder->query($request);
        $accounts = $userChannelAccounts->get();

        foreach ($accounts as $account) {
            $channelCode = $account->channel_code;
            if ($channelCode == Channel::CODE_MAYA) {
                SyncMayaAccountJob::dispatch($account->id, "init");
            } else {
                if (
                    !Redis::set(
                        "gcash:account:sync:{$account->id}",
                        1,
                        "EX",
                        60,
                        "NX"
                    )
                ) {
                    continue;
                }
                SyncGcashAccount::dispatch($account->id, "init");
            }
        }

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
