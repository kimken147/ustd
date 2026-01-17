<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminCreateUserRequest;
use App\Http\Requests\ListUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\User as UserResource;
use App\Http\Resources\UserCollection;
use App\Model\Channel;
use App\Model\ChannelGroup;
use App\Model\FakeCryptoWallet;
use App\Model\FeatureToggle;
use App\Model\Permission;
use App\Model\User;
use App\Model\User as UserModel;
use App\Model\UserChannel;
use App\Model\UserChannelAccount;
use App\Model\Wallet;
use App\Model\WalletHistory;
use App\Model\TransactionGroup;
use App\Model\Device;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\PermissionUtil;
use App\Utils\UserUtil;
use App\Utils\WalletUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

use Throwable;

class ProviderController extends Controller
{

    /**
     * @var UserUtil
     */
    private $user;

    public function __construct(UserUtil $user)
    {
        $this->user = $user;

        $this->middleware(['permission:' . Permission::ADMIN_CREATE_PROVIDER])->only('store');
    }

    public function index(ListUserRequest $request)
    {
        $providers = UserModel::where('role', UserModel::ROLE_PROVIDER);

        if ($request->has('no_paginate')) {
            $providers = $providers->with(['userChannels' => function ($query) {
                $query->where('user_channels.status', UserChannel::STATUS_ENABLED);
            }])->get(['id', 'username', 'name']);
            return response()->json(['data' => $providers]);
        }

        $providers->when($request->tag_ids, function ($query) use ($request) {
            $query->whereHas('tags', function ($q) use ($request) {
                $q->whereIn('tags.id', $request->tag_ids);
            });
        });

        $providers->with('wallet', 'parent', 'tags', 'descendants', 'controlDownlines', 'todaySuccessPaufenTransactions', 'todaySuccessWithdraws')
            ->latest('id');

        $providers->when($request->provider_name_or_username, function ($query, $nameOrUsername) {
            $query->where('username', 'like', '%' . $nameOrUsername . '%');
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

        foreach (
            [
                'google2fa_enable',
                'agent_enable',
                'deposit_enable',
                'withdraw_enable',
                'withdraw_profit_enable',
                'transaction_enable',
                'balance_transfer_enable',
                'cancel_order_enable',
                "paufen_deposit_enable"
            ] as $booleanFilter
        ) {
            $providers->when(!is_null($request->$booleanFilter), function ($query) use ($request, $booleanFilter) {
                $query->where($booleanFilter, $request->$booleanFilter);
            });
        }

        $providers = $providers->paginate(20)->appends($request->query->all());

        return UserCollection::make($providers)
            ->additional([
                'meta' => [
                    'provider_todays_amount_enable' => FeatureToggle::where('id', FeatureToggle::VISIABLE_TODAYS_PROVIDER_TRANSACTIONS_AMOUNT)->first('enabled'),
                ],
            ]);
    }

    public function resetGoogle2faSecret(
        UserModel $provider,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request
    ) {
        $google2faSecret = DB::transaction(function () use (
            $provider,
            $notificationUtil,
            $whitelistedIpManager,
            $request
        ) {
            $provider->update([
                'google2fa_secret' => $google2faSecret = $this->user->generateGoogle2faSecret(),
            ]);

            $notificationUtil->notifyAdminResetGoogle2faSecret(
                auth()->user()->realUser(),
                $provider,
                $whitelistedIpManager->extractIpFromRequest($request)
            );

            return $google2faSecret;
        });

        return UserResource::make($provider->load('wallet', 'parent'))
            ->withCredentials(['google2fa_secret' => $google2faSecret])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function resetPassword(
        UserModel $provider,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request
    ) {
        $password = DB::transaction(function () use ($provider, $notificationUtil, $whitelistedIpManager, $request) {
            $provider->update([
                'password' => Hash::make($password = $request->input('password', $this->user->generatePassword())),
            ]);

            $notificationUtil->notifyAdminResetPassword(
                auth()->user()->realUser(),
                $provider,
                $whitelistedIpManager->extractIpFromRequest($request)
            );

            return $password;
        });

        return UserResource::make($provider->load('wallet', 'parent'))
            ->withCredentials(['password' => $password])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(UserModel $provider, FeatureToggleRepository $featureToggleRepository)
    {
        $provider->load(['wallet', 'parent', 'descendants', 'controlDownlines']);
        $provider->load(["userChannels" => function ($q) {
            $q->whereHas("channelGroup.channel", function ($q) {
                $q->where("third_exclusive_enable", false);
            });
        }]);
        return (UserResource::make($provider)
            ->additional([
                'meta' => [
                    'exchange_feature_enabled' => $featureToggleRepository->enabled(FeatureToggle::EXCHANGE_MODE),
                ],
            ])
        );
    }

    public function store(
        AdminCreateUserRequest $request,
        BCMathUtil $bcMath,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $this->abortIfUsernameNotAlnum($request->username);
        $this->abortIfUsernameAlreadyExists($request->username);

        $agent = null;

        abort_if(
            $request->agent_id
                && !($agent = $this->user->findProviderWithId($request->agent_id)),
            Response::HTTP_BAD_REQUEST,
            __('common.Agent not found')
        );

        abort_if(
            !empty($agent) && $agent->agent_enable != UserModel::STATUS_ENABLE,
            Response::HTTP_BAD_REQUEST,
            __('common.Agent functionality is not enabled')
        );

        if ($agent) {
            foreach ($request->input('user_channels', []) as $userChannel) {
                // ignore nulls
                if (!isset($userChannel['fee_percent'])) {
                    continue;
                }

                $agentUserChannel = UserChannel::where([
                    ['channel_group_id', $userChannel['channel_group_id']],
                    ['user_id', $agent->getKey()],
                ])->first();

                abort_if(!$agentUserChannel, Response::HTTP_BAD_REQUEST, __('channel.Parent user channel not found'));

                // 以下程式碼若執行代表請求中一定有設定非 null 的手續費

                abort_if(
                    $agentUserChannel->status === UserChannel::STATUS_DISABLED,
                    Response::HTTP_BAD_REQUEST,
                    __('channel.Parent user channel not enabled')
                );

                // 上級一定要設定手續費
                abort_if(
                    is_null($agentUserChannel->fee_percent),
                    Response::HTTP_BAD_REQUEST,
                    __('channel.Invalid fee')
                );

                // 上下級都必須為 0
                abort_if(
                    ($agentUserChannel->fee_percent == 0 && $userChannel['fee_percent'] != 0)
                        || ($agentUserChannel->fee_percent != 0 && $userChannel['fee_percent'] == 0),
                    Response::HTTP_BAD_REQUEST,
                    __('channel.Invalid fee')
                );

                // 其他狀況
                abort_if(
                    $bcMath->gt($userChannel['fee_percent'], $agentUserChannel->fee_percent),
                    Response::HTTP_BAD_REQUEST,
                    __('channel.Invalid fee')
                );
            }
        }

        $password = $request->password ?? $this->user->generatePassword();
        $google2faSecret = $this->user->generateGoogle2faSecret();

        $provider = DB::transaction(function () use (
            $request,
            $agent,
            $password,
            $google2faSecret,
            $featureToggleRepository
        ) {
            /** @var User $agent */
            $accountMode = $agent ? $agent->account_mode : UserModel::ACCOUNT_MODE_GENERAL;

            if ($request->credit_mode_enable) {
                $accountMode = UserModel::ACCOUNT_MODE_CREDIT;
            }

            if ($request->deposit_mode_enable) {
                $accountMode = UserModel::ACCOUNT_MODE_DEPOSIT;
            }

            /** @var UserModel $provider */
            $provider = UserModel::create([
                'role'                  => UserModel::ROLE_PROVIDER,
                'status'                => UserModel::STATUS_ENABLE,
                'agent_enable'          => $request->agent_enable ?? true,
                'google2fa_enable'      => $request->google2fa_enable ?? false,
                'deposit_enable'        => $request->deposit_enable ?? false,
                'paufen_deposit_enable' => $request->paufen_deposit_enable ?? false,
                'withdraw_enable'       => ($request->withdraw_enable ?? false) && (optional($agent)->withdraw_enable ?? true),
                'withdraw_profit_enable' => ($request->withdraw_profit_enable ?? false) && (optional($agent)->withdraw_profit_enable ?? true),
                'transaction_enable'    => ($request->transaction_enable ?? true) && (optional($agent)->transaction_enable ?? true),
                'account_mode'          => $accountMode,
                'google2fa_secret'      => $google2faSecret,
                'password'              => Hash::make($password),
                'secret_key'            => $this->user->generateSecretKey(),
                'name'                  => $request->name,
                'username'              => $request->username,
                'parent_id'             => isset($agent) ? $agent->getKey() : null,
                'phone'                 => $request->phone,
                'contact'               => $request->contact,
                'currency'              => (optional($agent)->currency ?? ''),
            ]);

            $shouldHaveInitialFrozenBalance = $featureToggleRepository->enabled(FeatureToggle::INITIAL_PROVIDER_FROZEN_BALANCE);
            $initialFrozenBalance = $featureToggleRepository->valueOf(
                FeatureToggle::INITIAL_PROVIDER_FROZEN_BALANCE,
                '0.00'
            );

            $provider->wallet()->create([
                'status'         => Wallet::STATUS_ENABLE,
                'balance'        => $request->balance ? $request->input('balance', 0) : '0.00',
                'frozen_balance' => $shouldHaveInitialFrozenBalance ? $initialFrozenBalance : '0.00',
                'withdraw_fee'   => $request->input('withdraw_fee', 0),
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
                $thirdExclusive = $channelGroup->channel->third_exclusive_enable;
                $provider->userChannels()->create([
                    'channel_group_id' => $channelGroupId,
                    'fee_percent'      => $feePercent = $request->set_fee_percent ? 0 : data_get($userChannelFeePercents, $channelGroupId, 0),
                    'min_amount'       => data_get($userChannelMinAmounts, $channelGroupId, null),
                    'max_amount'       => data_get($userChannelMaxAmounts, $channelGroupId, null),
                    'status'           => is_null($feePercent) || $thirdExclusive ? Channel::STATUS_DISABLE : Channel::STATUS_ENABLE,
                    'floating_enable'  => false,
                ]);
            }

            $device = Device::firstOrCreate([
                'user_id' => $provider->id,
                'name' => $provider->name
            ]);

            return $provider;
        });

        return UserResource::make($provider->refresh()->load('wallet', 'parent', 'userChannels'))
            ->withCredentials(['password' => $password, 'google2fa_secret' => $google2faSecret]);
    }

    private function abortIfUsernameNotAlnum(string $username)
    {
        abort_if(
            !ctype_alnum($username),
            Response::HTTP_BAD_REQUEST,
            __('common.Username can only be alphanumeric')
        );
    }

    private function abortIfUsernameAlreadyExists(string $username)
    {
        abort_if(
            $this->user->usernameAlreadyExists($username),
            Response::HTTP_BAD_REQUEST,
            __('common.Duplicate username')
        );
    }

    public function update(
        UpdateUserRequest $request,
        UserModel $provider,
        BCMathUtil $bcMath,
        WalletUtil $wallet,
        PermissionUtil $permissionUtil,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager
    ) {
        abort_if(
            $provider->role !== User::ROLE_PROVIDER,
            Response::HTTP_BAD_REQUEST,
            __('user.Invalid role')
        );

        abort_if(
            !$provider->isRoot() && $request->credit_mode_enable,
            Response::HTTP_BAD_REQUEST,
            __('user.Credit mode can only been set from root')
        );

        abort_if(
            !$provider->isRoot() && $request->deposit_mode_enable,
            Response::HTTP_BAD_REQUEST,
            __('user.Deposit mode can only been set from root')
        );

        if ($request->username) {
            $this->abortIfUsernameNotAlnum($request->username);
            $this->abortIfUsernameAlreadyExists($request->username);
        }

        if ($request->balance_delta || $request->profit_delta || $request->frozen_balance_delta) {
            $permissionUtil->abortForbiddenIfPermissionDenied(
                auth()->user(),
                Permission::ADMIN_UPDATE_PROVIDER_WALLET
            );
        }

        if ($request->has('tag_ids')) {
            $provider->tags()->sync($request->tag_ids);
        }

        if (!$request->has('balance_delta') && !$request->has('profit_delta') && !$request->has('frozen_balance_delta')) {
            $permissionUtil->abortForbiddenIfPermissionDenied(
                auth()->user(),
                Permission::ADMIN_UPDATE_PROVIDER
            );
        }

        DB::transaction(function () use (
            $provider,
            $request,
            $wallet,
            $bcMath,
            $notificationUtil,
            $whitelistedIpManager
        ) {
            foreach (
                [
                    'withdraw_fee',
                    'withdraw_profit_fee',
                    'withdraw_min_amount',
                    'withdraw_max_amount',
                    'withdraw_profit_min_amount',
                    'withdraw_profit_max_amount',
                    'agency_withdraw_min_amount',
                    'agency_withdraw_max_amount'
                ] as $walletAttribute
            ) {
                if ($request->has($walletAttribute)) {
                    $provider->wallet->$walletAttribute = $request->input(
                        $walletAttribute,
                        $provider->wallet->$walletAttribute
                    );
                }
            }

            $provider->wallet->save();

            if ($request->balance_delta || $request->profit_delta || $request->frozen_balance_delta) {
                if ($request->balance_delta) $type = WalletHistory::TYPE_SYSTEM_ADJUSTING;
                if ($request->profit_delta) $type = WalletHistory::TYPE_SYSTEM_ADJUSTING_PROFIT;
                if ($request->frozen_balance_delta) $type = WalletHistory::TYPE_SYSTEM_ADJUSTING_FROZEN_BALANCE;

                $updated = $wallet->conflictAwaredBalanceUpdate(
                    Wallet::lockForUpdate()->find($provider->wallet->id),
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
                    $provider,
                    $delta,
                    $request->note ?? '',
                    $whitelistedIpManager->extractIpFromRequest($request)
                );
            }

            $provider->update($request->only([
                'agent_enable',
                'google2fa_enable',
                'deposit_enable',
                'name',
                'username',
                'status',
                'withdraw_enable',
                'withdraw_profit_enable',
                'transaction_enable',
                'phone',
                'contact',
                'paufen_deposit_enable',
                'balance_transfer_enable',
                'ready_for_matching',
                'exchange_mode_enable',
                'cancel_order_enable',
                'control_downline'
            ]));

            if ($request->boolean('exchange_mode_enable')) {
                $now = now();

                DB::table((new FakeCryptoWallet())->getTable())->insertOrIgnore([
                    [
                        'user_id' => $provider->getKey(),
                        'currency' => FakeCryptoWallet::CURRENCY_BTC,
                        'balance' => '0.00',
                        'created_at' => $now,
                        'updated_at' => $now
                    ],
                    [
                        'user_id' => $provider->getKey(),
                        'currency' => FakeCryptoWallet::CURRENCY_ETH,
                        'balance' => '0.00',
                        'created_at' => $now,
                        'updated_at' => $now
                    ],
                    [
                        'user_id' => $provider->getKey(),
                        'currency' => FakeCryptoWallet::CURRENCY_USDT,
                        'balance' => '0.00',
                        'created_at' => $now,
                        'updated_at' => $now
                    ],
                ]);
            }

            if ($request->has('credit_mode_enable')) {
                $originalAccountMode = $provider->account_mode;
                $accountMode = $request->credit_mode_enable ? UserModel::ACCOUNT_MODE_CREDIT : UserModel::ACCOUNT_MODE_GENERAL;

                $provider->descendants()->update(['account_mode' => $accountMode]);
                $provider->update(['account_mode' => $accountMode]);

                if ($originalAccountMode === UserModel::ACCOUNT_MODE_DEPOSIT) {
                    UserChannelAccount::whereHas('user', function ($builder) use ($provider) {
                        $builder->whereDescendantOrSelf($provider);
                    })
                        ->getQuery()
                        ->leftJoin('wallets', 'wallets.user_id', '=', 'user_channel_accounts.user_id')
                        ->update(['wallet_id' => DB::raw('wallets.id')]);
                }
            } else {
                if ($request->has('deposit_mode_enable')) {
                    $accountMode = $request->deposit_mode_enable ? UserModel::ACCOUNT_MODE_DEPOSIT : UserModel::ACCOUNT_MODE_GENERAL;

                    $provider->descendants()->update(['account_mode' => $accountMode]);
                    $provider->update(['account_mode' => $accountMode]);

                    if ($request->deposit_mode_enable) {
                        UserChannelAccount::whereHas('user', function ($builder) use ($provider) {
                            $builder->whereDescendantOrSelf($provider);
                        })
                            ->update(['wallet_id' => $provider->wallet->getKey()]);
                    } else {
                        UserChannelAccount::whereHas('user', function ($builder) use ($provider) {
                            $builder->whereDescendantOrSelf($provider);
                        })
                            ->getQuery()
                            ->leftJoin('wallets', 'wallets.user_id', '=', 'user_channel_accounts.user_id')
                            ->update(['wallet_id' => DB::raw('wallets.id')]);
                    }
                }
            }
        });

        return UserResource::make($provider->load('wallet', 'parent', 'descendants', 'controlDownlines'));
    }

    public function updateControlDownlines(Request $request, UserModel $provider)
    {
        $downlines = [];
        foreach ($request->input('downlines') as $id) {
            $downlines[] = ['parent_id' => $provider->id, 'downline_id' => $id];
        }
        try {
            DB::beginTransaction();
            DB::table('control_downlines')->where('parent_id', $provider->id)->delete();
            DB::table('control_downlines')->insert($downlines);
            DB::commit();
        } catch (Throwable $throw) {
            DB::rollback();
        }
        return $provider;
    }

    public function destroy(UserModel $provider, PermissionUtil $permissionUtil)
    {
        $permissionUtil->abortForbiddenIfPermissionDenied(
            auth()->user(),
            Permission::ADMIN_UPDATE_PROVIDER
        );

        abort_if(
            $provider->role !== User::ROLE_PROVIDER,
            Response::HTTP_BAD_REQUEST,
            __('user.Invalid role')
        );

        abort_if(
            $this->user->checkLowerAgentIsNotDelete($provider->id),
            Response::HTTP_BAD_REQUEST,
            __('user.Lower agent is not delete')
        );

        DB::transaction(function () use ($provider) {
            //刪除碼商的收款卡
            $UserChannelAccountQuery = UserChannelAccount::where('user_id', $provider->id);

            abort_if($UserChannelAccountQuery->count() > 0, Response::HTTP_FORBIDDEN, '请先移除底下收付款账号，再尝试删除');

            //刪除商戶的交易專線(快沖專線)
            $TransactionGroupQuety = TransactionGroup::where('worker_id', $provider->id);
            if ($TransactionGroupQuety->count() > 0) {
                abort_if(!$TransactionGroupQuety->delete(), Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $provider->update(['status' => User::STATUS_DISABLE]);
            abort_if(!$provider->delete(), Response::HTTP_INTERNAL_SERVER_ERROR);
        });

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
