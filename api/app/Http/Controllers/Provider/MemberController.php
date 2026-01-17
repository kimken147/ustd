<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\ListUserRequest;
use App\Http\Resources\User as UserResource;
use App\Http\Resources\UserCollection;
use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\FeatureToggle;
use App\Models\User;
use App\Models\User as UserModel;
use App\Models\UserChannel;
use App\Models\Device;
use App\Models\Wallet as WalletModel;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\UserUtil;
use App\Utils\WalletUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MemberController extends Controller
{

    /**
     * @var UserUtil
     */
    private $user;

    public function __construct(UserUtil $user)
    {
        $this->user = $user;
    }

    public function index(ListUserRequest $request)
    {
        $members = UserModel::where('role', UserModel::ROLE_PROVIDER)
            ->where('parent_id', auth()->user()->getKey())
            ->with('wallet')
            ->latest('id');

        $members->when($request->name_or_username, function ($query, $nameOrUsername) {
            $query->where(function ($query) use ($nameOrUsername) {
                $query->where('name', 'like', "%$nameOrUsername%")
                    ->orWhere('username', $nameOrUsername);
            });
        })
            ->when(!is_null($request->status), function ($query) use ($request) {
                $query->where('status', $request->status);
            });

        foreach ([
            'google2fa_enable', 'agent_enable', 'deposit_enable', 'withdraw_enable', 'withdraw_profit_enable', 'transaction_enable'
        ] as $booleanFilter) {
            $members->when(!is_null($request->$booleanFilter), function ($query) use ($request, $booleanFilter) {
                $query->where($booleanFilter, $request->$booleanFilter);
            });
        }

        $members = $members->paginate(20)->appends($request->query->all());

        return UserCollection::make($members);
    }

    public function show(UserModel $member)
    {
        $this->abortIfMemberNotDirectChildren($member);

        return UserResource::make($member->load('wallet', 'userChannels'));
    }

    private function abortIfMemberNotDirectChildren(UserModel $member)
    {
        abort_if(
            !optional($member->parent)->is(auth()->user()),
            Response::HTTP_FORBIDDEN
        );
    }

    public function store(
        CreateUserRequest $request,
        BCMathUtil $bcMath,
        FeatureToggleRepository $featureToggleRepository
    ) {
        abort_if(
            $featureToggleRepository->enabled(FeatureToggle::DISABLE_PROVIDER_CREATE_NEW_MEMBER),
            Response::HTTP_BAD_REQUEST,
            '请联络客服建立下级帐号'
        );

        $this->abortIfUsernameNotAlnum($request->username);
        $this->abortIfUsernameAlreadyExists($request->username);

        $agent = auth()->user();

        abort_if(
            $agent->agent_enable != UserModel::STATUS_ENABLE,
            Response::HTTP_BAD_REQUEST,
            __('common.Agent functionality is not enabled')
        );

        foreach ($request->input('user_channels', []) as $userChannelRequest) {
            // ignore nulls
            if (!isset($userChannelRequest['fee_percent'])) {
                continue;
            }

            $agentUserChannel = UserChannel::where([
                ['channel_group_id', $userChannelRequest['channel_group_id']],
                ['user_id', auth()->user()->getKey()],
            ])->first();

            abort_if(!$agentUserChannel, Response::HTTP_BAD_REQUEST, __('channel.Parent user channel not found'));

            // 以下程式碼若執行代表請求中一定有設定非 null 的手續費

            abort_if(
                $agentUserChannel->status === UserChannel::STATUS_DISABLED,
                Response::HTTP_BAD_REQUEST,
                __('channel.Please enable your channel first')
            );

            // 上級一定要設定手續費
            abort_if(
                is_null($agentUserChannel->fee_percent),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );

            // 上下級都必須為 0
            abort_if(
                ($agentUserChannel->fee_percent == 0 && $userChannelRequest['fee_percent'] != 0)
                    || ($agentUserChannel->fee_percent != 0 && $userChannelRequest['fee_percent'] == 0),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );

            // 其他狀況
            abort_if(
                $bcMath->gt($userChannelRequest['fee_percent'], $agentUserChannel->fee_percent),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );
        }

        $password = $this->user->generatePassword();
        $google2faSecret = $this->user->generateGoogle2faSecret();

        $member = DB::transaction(function () use (
            $request,
            $agent,
            $password,
            $google2faSecret,
            $bcMath,
            $featureToggleRepository
        ) {
            /** @var UserModel $member */
            $member = UserModel::create([
                'role'               => UserModel::ROLE_PROVIDER,
                'status'             => UserModel::STATUS_ENABLE,
                'agent_enable'       => false,
                'google2fa_enable'   => false,
                'deposit_enable'     => $agent->deposit_enable,
                'withdraw_enable'    => $agent->withdraw_enable,
                'withdraw_profit_enable' => $agent->withdraw_profit_enable,
                'transaction_enable' => $agent->transaction_enable,
                'account_mode'       => $agent->creditModeEnabled() ? User::ACCOUNT_MODE_CREDIT : User::ACCOUNT_MODE_GENERAL,
                'google2fa_secret'   => $google2faSecret,
                'password'           => Hash::make($password),
                'secret_key'         => $this->user->generateSecretKey(),
                'name'               => $request->name,
                'username'           => $request->username,
                'parent_id'          => $agent->getKey(),
                'phone'              => $request->phone,
                'contact'            => $request->contact,
                'currency'           => (optional($agent)->currency ?? ''),
                "paufen_deposit_enable" => true
            ]);

            $shouldHaveInitialFrozenBalance = $featureToggleRepository->enabled(FeatureToggle::INITIAL_PROVIDER_FROZEN_BALANCE);
            $initialFrozenBalance = $featureToggleRepository->valueOf(
                FeatureToggle::INITIAL_PROVIDER_FROZEN_BALANCE,
                '0.00'
            );

            $member->wallet()->create([
                'status'         => WalletModel::STATUS_ENABLE,
                'balance'        => 0,
                'frozen_balance' => $shouldHaveInitialFrozenBalance ? $initialFrozenBalance : '0.00',
                'withdraw_fee'   => $agent->wallet->withdraw_fee,
            ]);

            $userChannelFeePercents = collect($request->input('user_channels', []))->pluck(
                'fee_percent',
                'channel_group_id'
            );
            $allChannelGroups = ChannelGroup::all()->keyBy('id');

            foreach ($allChannelGroups as $channelGroupId => $channelGroup) {
                $member->userChannels()->create([
                    'channel_group_id' => $channelGroupId,
                    'fee_percent'      => $feePercent = data_get($userChannelFeePercents, $channelGroupId, null),
                    'min_amount'       => null,
                    'max_amount'       => null,
                    'status'           => Channel::STATUS_DISABLE,
                    'floating_enable'  => false,
                ]);
            }

            // 新增設備號
            $device = Device::firstOrCreate([
                'user_id' => $member->id,
                'name' => $member->name
            ]);

            return $member;
        });

        return UserResource::make($member->load('wallet', 'userChannels'))
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
        Request $request,
        UserModel $member,
        BCMathUtil $bcMath,
        WalletUtil $wallet,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $this->validate($request, [
            'balance_delta' => 'numeric|min:1',
        ]);

        $this->abortIfMemberNotDirectChildren($member);

        DB::transaction(function () use ($member, $wallet, $bcMath, $request, $featureToggleRepository) {
            if ($request->balance_delta) {
                abort_if(
                    !$featureToggleRepository->enabled(FeatureToggle::PROVIDER_TRANSFER_BALANCE),
                    Response::HTTP_BAD_REQUEST,
                    __('member.Transfer balance feature not enabled')
                );

                abort_if(
                    $bcMath->lt(auth()->user()->wallet->available_balance, $bcMath->abs($request->balance_delta)),
                    Response::HTTP_BAD_REQUEST,
                    __('wallet.InsufficientAvailableBalance')
                );

                $updatedRows = $wallet->transfer(
                    auth()->user()->wallet, // from
                    $member->wallet, // to
                    $request->input('balance_delta', 0), // amount
                    auth()->user(), // operator
                    $request->note
                );

                abort_if(
                    $updatedRows !== 2, // from + to = 2
                    Response::HTTP_CONFLICT,
                    __('common.Wallet update conflicts, please try again later')
                );
            }
        });

        return UserResource::make($member->load('wallet', 'parent'));
    }
}
