<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCreateUserRequest;
use App\Http\Requests\ListUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\User as UserResource;
use App\Http\Resources\UserCollection;
use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\FeatureToggle;
use App\Models\Permission;
use App\Models\User;
use App\Models\User as UserModel;
use App\Models\UserChannel;
use App\Models\Wallet;
use App\Models\WalletHistory;
use App\Repository\FeatureToggleRepository;
use App\Utils\AmountDisplayTransformer;
use App\Services\UserManagementService;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\PermissionUtil;
use App\Utils\UserUtil;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use App\Utils\ChannelCheckUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MerchantController extends Controller
{

    /**
     * @var UserUtil
     */
    private $user;

    private UserManagementService $userManagementService;

    public function __construct(UserUtil $user, UserManagementService $userManagementService)
    {
        $this->user = $user;
        $this->userManagementService = $userManagementService;

        $this->middleware(['permission:' . Permission::ADMIN_CREATE_MERCHANT])->only('store');
    }

    public function index(ListUserRequest $request)
    {
        $merchants = UserModel::where('role', UserModel::ROLE_MERCHANT);

        if ($request->has('no_paginate')) {
            $merchants = $merchants->with(['userChannels' => function ($query) {
                $query->where('user_channels.status', UserChannel::STATUS_ENABLED);
            }])->get(['id', 'username', 'name']);
            return response()->json(['data' => $merchants]);
        }

        $merchants->with('wallet', 'parent', 'tags')
            ->latest('id');

        $merchants->when($request->merchant_name_or_username, function ($query, $nameOrUsername) {
            $query->where(function ($query) use ($nameOrUsername) {
                $query->whereIn('username', $nameOrUsername);
            });
        })
            ->when($request->agent_name_or_username, function ($query, $agentNameOrUsername) {
                $query->whereHas('parent', function ($agent) use ($agentNameOrUsername) {
                    $agent->where('name', 'like', "%$agentNameOrUsername%")
                        ->orWhere('username', $agentNameOrUsername);
                });
            })
            ->when(!is_null($request->status), function ($query) use ($request) {
                $query->where('status', $request->status);
            });

        $merchants->when($request->tag_ids, function ($query) use ($request) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->whereIn('tags.id', $request->tag_ids);
            });
        });

        foreach (['google2fa_enable', 'agent_enable', 'withdraw_enable', 'transaction_enable', 'withdraw_google2fa_enable', 'third_channel_enable'] as $booleanFilter) {
            $merchants->when(!is_null($request->$booleanFilter), function ($query) use ($request, $booleanFilter) {
                $query->where($booleanFilter, $request->$booleanFilter);
            });
        }

        $stats = Wallet::whereIn('user_id', (clone $merchants)->select(['id']))->first([
            DB::raw('SUM(balance) AS total_balance'),
            DB::raw('SUM(frozen_balance) AS total_frozen_balance'),
        ]);

        $perPage = $request->input('per_page', 20);
        $merchants = $merchants->paginate($perPage)->appends($request->query->all());

        return UserCollection::make($merchants)->additional([
            'meta' => [
                'total_balance'        => AmountDisplayTransformer::transform($stats->total_balance) ?? '0.00',
                'total_frozen_balance' => AmountDisplayTransformer::transform($stats->total_frozen_balance) ?? '0.00',
            ],
        ]);
    }

    public function resetGoogle2faSecret(
        UserModel $merchant,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request
    ) {
        return $this->userManagementService->resetGoogle2faSecret($merchant, $notificationUtil, $whitelistedIpManager, $request);
    }

    public function resetPassword(
        UserModel $merchant,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request
    ) {
        return $this->userManagementService->resetPassword($merchant, $notificationUtil, $whitelistedIpManager, $request);
    }

    public function resetSecret(
        UserModel $merchant,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        PermissionUtil $permissionUtil,
        Request $request
    ) {
        $permissionUtil->abortForbiddenIfPermissionDenied(
            auth()->user(),
            Permission::ADMIN_MANAGE_MERCHANT_SECRET
        );

        $secret = DB::transaction(function () use ($merchant, $notificationUtil, $whitelistedIpManager, $request) {
            $merchant->update([
                'secret_key' => $secret = $this->user->generateSecretKey(),
            ]);

            $notificationUtil->notifyAdminResetSecret(
                auth()->user()->realUser(),
                $merchant,
                $whitelistedIpManager->extractIpFromRequest($request)
            );

            return $secret;
        });

        return UserResource::make($merchant->load('wallet', 'parent'))
            ->withCredentials(['secret' => $secret])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(UserModel $merchant, FeatureToggleRepository $featureToggleRepository)
    {
        $agencyWithdrawEnabled = $featureToggleRepository->enabled(FeatureToggle::ENABLE_AGENCY_WITHDRAW);

        return UserResource::make($merchant->load('wallet', 'parent', 'userChannels'))->additional([
            'meta' => [
                'agency_withdraw_enabled' => $agencyWithdrawEnabled,
            ],
        ]);
    }

    public function store(AdminCreateUserRequest $request, BCMathUtil $bcMath)
    {
        $this->userManagementService->abortIfUsernameNotAlnum($request->username);
        $this->userManagementService->abortIfUsernameAlreadyExists($request->username);

        $agent = null;

        abort_if(
            $request->agent_id
                && !($agent = $this->user->findMerchantWithId($request->agent_id)),
            Response::HTTP_BAD_REQUEST,
            __('common.Agent not found')
        );

        abort_if(
            !empty($agent) && $agent->agent_enable != UserModel::STATUS_ENABLE,
            Response::HTTP_BAD_REQUEST,
            __('common.Agent functionality is not enabled')
        );

        if ($agent) {
            $this->userManagementService->validateChannelFees($agent, $request->input('user_channels', []), $bcMath, 'lt');
        }

        $password = $this->user->generatePassword();
        $google2faSecret = $this->user->generateGoogle2faSecret();
        $secretKey = $this->user->generateSecretKey();

        $merchant = DB::transaction(function () use (
            $request,
            $agent,
            $password,
            $google2faSecret,
            $secretKey
        ) {

            $merchant = UserModel::create([
                'role'                          => UserModel::ROLE_MERCHANT,
                'status'                        => UserModel::STATUS_ENABLE,
                'agent_enable'                  => $request->agent_enable ?? false,
                'google2fa_enable'              => $request->google2fa_enable ?? false,
                'withdraw_review_enable'        => $request->withdraw_review_enable ?? false,
                'withdraw_google2fa_enable'     => $request->withdraw_google2fa_enable ?? false,
                'withdraw_enable'               => $request->input('withdraw_enable', (optional($agent)->withdraw_enable ?? false)),
                'paufen_withdraw_enable'        => $request->input('paufen_withdraw_enable', (optional($agent)->paufen_withdraw_enable ?? false)),
                'agency_withdraw_enable'        => $request->input('agency_withdraw_enable', (optional($agent)->agency_withdraw_enable ?? false)),
                'paufen_agency_withdraw_enable' => $request->input('paufen_agency_withdraw_enable', (optional($agent)->paufen_agency_withdraw_enable ?? false)),
                'transaction_enable'            => ($request->transaction_enable ?? true) && (optional($agent)->transaction_enable ?? true),
                'third_channel_enable'          => ($request->third_channel_enable ?? false) && (optional($agent)->third_channel_enable ?? false),
                'google2fa_secret'              => $google2faSecret,
                'password'                      => Hash::make($password),
                'secret_key'                    => $secretKey,
                'name'                          => $request->name,
                'username'                      => $request->username,
                'parent_id'                     => isset($agent) ? $agent->getKey() : null,
                'phone'                         => $request->phone,
                'contact'                       => $request->contact,
                'currency'                      => (optional($agent)->currency ?? $request->input('currency', '')),
            ]);

            $merchant->wallet()->create([
                'status'              => Wallet::STATUS_ENABLE,
                'balance'             => 0,
                'frozen_balance'      => 0,
                'withdraw_fee'        => $request->input('withdraw_fee', 0),
                'withdraw_fee_percent' => $request->input('withdraw_fee_percent', 0),
                'additional_withdraw_fee' => $request->input('additional_withdraw_fee', 0),
                'agency_withdraw_fee' => $request->input('agency_withdraw_fee', 0),
                'agency_withdraw_fee_dollar' => $request->input('agency_withdraw_fee_dollar', 0),
                'additional_agency_withdraw_fee' => $request->input('additional_agency_withdraw_fee', 0),
                'withdraw_min_amount' => $request->input('withdraw_min_amount', null),
                'withdraw_max_amount' => $request->input('withdraw_max_amount', null),
                'agency_withdraw_min_amount' => $request->input('agency_withdraw_min_amount', null),
                'agency_withdraw_max_amount' => $request->input('agency_withdraw_max_amount', null)

            ]);

            $userChannelFeePercents = collect($request->input('user_channels', []))->pluck(
                'fee_percent',
                'channel_group_id'
            );
            $userChannelMinAmounts = collect($request->input('user_channels', []))->pluck(
                'min_amount',
                'channel_group_id'
            );
            $userChannelMaxAmounts = collect($request->input('user_channels', []))->pluck(
                'max_amount',
                'channel_group_id'
            );
            $allChannelGroups = ChannelGroup::all()->keyBy('id');

            foreach ($allChannelGroups as $channelGroupId => $channelGroup) {
                $merchant->userChannels()->create([
                    'channel_group_id' => $channelGroupId,
                    'fee_percent'      => $feePercent = data_get($userChannelFeePercents, $channelGroupId, null),
                    'min_amount'       => data_get($userChannelMinAmounts, $channelGroupId, null),
                    'max_amount'       => data_get($userChannelMaxAmounts, $channelGroupId, null),
                    'status'           => is_null($feePercent) ? Channel::STATUS_DISABLE : Channel::STATUS_ENABLE,
                    'floating_enable'  => false,
                ]);
            }

            return $merchant;
        });

        return UserResource::make($merchant->refresh()->load('wallet', 'parent', 'userChannels'))
            ->withCredentials(['password' => $password, 'google2fa_secret' => $google2faSecret, 'secret_key' => $secretKey]);
    }

    public function update(
        UpdateUserRequest $request,
        UserModel $merchant,
        BCMathUtil $bcMath,
        WalletUtil $wallet,
        PermissionUtil $permissionUtil,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        ChannelCheckUtil $channelcheckUtil
    ) {
        abort_if(
            $merchant->role !== User::ROLE_MERCHANT,
            Response::HTTP_BAD_REQUEST,
            __('user.Invalid role')
        );

        if ($request->username) {
            $this->userManagementService->abortIfUsernameNotAlnum($request->username);
            $this->userManagementService->abortIfUsernameAlreadyExists($request->username);
        }

        if ($request->balance_delta || $request->frozen_balance_delta) {
            $permissionUtil->abortForbiddenIfPermissionDenied(
                auth()->user(),
                Permission::ADMIN_UPDATE_MERCHANT_WALLET
            );
        }

        if ($request->has('include_self_providers')) {
            $permissionUtil->abortForbiddenIfPermissionDenied(
                auth()->user(),
                Permission::ADMIN_MANAGE_MERCHANT_THIRD_CHANNEL
            );
        }

        if (!$request->has('balance_delta') && !$request->has('frozen_balance_delta') && !$request->has('include_self_providers')) {
            $permissionUtil->abortForbiddenIfPermissionDenied(
                auth()->user(),
                Permission::ADMIN_UPDATE_MERCHANT
            );
        }

        if ($request->parent_id) {
            //检查自身开启功能
            $channelcheckUtil->abortForbiddenIfcheckChannelFailed(
                $request->id,
                $request->parent_id
            );
        }

        if ($request->has('tag_ids')) {
            $merchant->tags()->sync($request->tag_ids);
        }

        DB::transaction(function () use (
            $merchant,
            $request,
            $wallet,
            $bcMath,
            $notificationUtil,
            $whitelistedIpManager
        ) {
            foreach (
                [
                    'withdraw_fee',
                    'withdraw_fee_percent',
                    'additional_withdraw_fee',
                    'agency_withdraw_fee',
                    'agency_withdraw_fee_dollar',
                    'additional_agency_withdraw_fee',
                    'withdraw_min_amount',
                    'withdraw_max_amount',
                    'agency_withdraw_min_amount',
                    'agency_withdraw_max_amount'
                ] as $walletAttribute
            ) {
                if ($request->has($walletAttribute)) {
                    $merchant->wallet->$walletAttribute = $request->input(
                        $walletAttribute,
                        $merchant->wallet->$walletAttribute
                    );
                }
            }

            $merchant->wallet->save();

            if ($request->balance_delta || $request->frozen_balance_delta) {
                if ($request->balance_delta) $type = WalletHistory::TYPE_SYSTEM_ADJUSTING;
                if ($request->frozen_balance_delta) $type = WalletHistory::TYPE_SYSTEM_ADJUSTING_FROZEN_BALANCE;

                $updated = $wallet->conflictAwaredBalanceUpdate(
                    Wallet::lockForUpdate()->find($merchant->wallet->id),
                    $delta = [
                        'balance'        => $request->input('balance_delta', 0),
                        'profit'         => $request->input('profit_delta', 0),
                        'frozen_balance' => $request->input('frozen_balance_delta', 0),
                    ],
                    $request->note,
                    $type
                );

                abort_if(!$updated, Response::HTTP_CONFLICT, __('common.Wallet update conflicts, please try again later'));

                $notificationUtil->notifyAdminUpdateBalance(
                    auth()->user()->realUser(),
                    $merchant,
                    $delta,
                    $request->note ?? '',
                    $whitelistedIpManager->extractIpFromRequest($request)
                );
            }

            if ($request->has('verify_daifu_account')) {
                $request->verify_daifu_account ? $merchant->addTag('verify_daifu_account') : $merchant->removeTag('verify_daifu_account');
            }

            $merchant->update($request->only([
                'agent_enable',
                'google2fa_enable',
                'name',
                'username',
                'status',
                'withdraw_enable',
                'withdraw_google2fa_enable',
                'transaction_enable',
                'phone',
                'contact',
                'paufen_withdraw_enable',
                'agency_withdraw_enable',
                'paufen_agency_withdraw_enable',
                'withdraw_review_enable',
                'usdt_rate',
                'parent_id',
                'third_channel_enable',
                'include_self_providers',
                'balance_limit'
            ]));
        });

        return UserResource::make($merchant->load('wallet', 'parent', 'tags'));
    }

    public function destroy(UserModel $merchant, PermissionUtil $permissionUtil)
    {
        $permissionUtil->abortForbiddenIfPermissionDenied(
            auth()->user(),
            Permission::ADMIN_UPDATE_MERCHANT
        );

        abort_if(
            $merchant->role !== User::ROLE_MERCHANT,
            Response::HTTP_BAD_REQUEST,
            __('user.Invalid role')
        );

        abort_if(
            $this->user->checkLowerAgentIsNotDelete($merchant->id),
            Response::HTTP_BAD_REQUEST,
            __('user.Lower agent is not delete')
        );

        DB::transaction(function () use ($merchant) {

            // 刪除商戶一併刪除交易快充專線
            $merchant->matchingDepositGroups()->delete();
            $merchant->transactionGroups()->delete();

            $merchant->update(['status' => User::STATUS_DISABLE]);
            abort_if(!$merchant->delete(), Response::HTTP_INTERNAL_SERVER_ERROR);
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
