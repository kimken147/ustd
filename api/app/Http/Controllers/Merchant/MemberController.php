<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\ListUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\Merchant\User as UserResource;
use App\Http\Resources\Merchant\UserCollection;
use App\Models\Channel;
use App\Models\ChannelGroup;
use App\Models\User as UserModel;
use App\Models\UserChannel;
use App\Models\Wallet as WalletModel;
use App\Utils\BCMathUtil;
use App\Utils\UserUtil;
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
        $members = UserModel::where('role', UserModel::ROLE_MERCHANT)
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

        foreach (['google2fa_enable', 'agent_enable', 'withdraw_enable', 'transaction_enable'] as $booleanFilter) {
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

    public function store(CreateUserRequest $request, BCMathUtil $bcMath)
    {
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
                $bcMath->lt($userChannelRequest['fee_percent'], $agentUserChannel->fee_percent),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );
        }

        $password = $this->user->generatePassword();
        $google2faSecret = $this->user->generateGoogle2faSecret();
        $secretKey = $this->user->generateSecretKey();

        $member = DB::transaction(function () use (
            $request,
            $agent,
            $password,
            $google2faSecret,
            $secretKey,
            $bcMath
        ) {
            $member = UserModel::create([
                'role'               => UserModel::ROLE_MERCHANT,
                'status'             => UserModel::STATUS_ENABLE,
                'agent_enable'       => $request->input('agent_enable', false),
                'google2fa_enable'   => true,
                'withdraw_enable'    => auth()->user()->withdraw_enable,
                'transaction_enable' => auth()->user()->transaction_enable,
                'agency_withdraw_enable' => auth()->user()->agency_withdraw_enable,
                'google2fa_secret'   => $google2faSecret,
                'password'           => Hash::make($password),
                'secret_key'         => $secretKey,
                'name'               => $request->name,
                'username'           => $request->username,
                'parent_id'          => $agent->getKey(),
                'phone'              => $request->phone,
                'contact'            => $request->contact,
                'currency'           => (optional($agent)->currency ?? ''),
                'withdraw_enable'               => $request->input('withdraw_enable', optional($agent)->withdraw_enable),
                'paufen_withdraw_enable'        => $request->input('paufen_withdraw_enable', optional($agent)->paufen_withdraw_enable),
                'agency_withdraw_enable'        => $request->input('agency_withdraw_enable', optional($agent)->agency_withdraw_enable),
                'paufen_agency_withdraw_enable' => $request->input('paufen_agency_withdraw_enable', optional($agent)->paufen_agency_withdraw_enable),

            ]);

            $member->wallet()->create([
                'status'              => WalletModel::STATUS_ENABLE,
                'balance'             => 0,
                'frozen_balance'      => 0,
                'withdraw_fee'        => $request->input('withdraw_fee', 0),
                'withdraw_fee_percent' => $request->input('withdraw_fee_percent', 0),
                'additional_withdraw_fee' => $request->input('additional_withdraw_fee', 0),
                'agency_withdraw_fee' => $request->input('agency_withdraw_fee', 0),
                'agency_withdraw_fee_dollar' => $request->input('agency_withdraw_fee_dollar', 0),
                'additional_agency_withdraw_fee' => $request->input('additional_agency_withdraw_fee', 0),
            ]);

            $userChannelFeePercents = collect($request->input('user_channels', []))->pluck('fee_percent', 'channel_group_id');
            $allChannelGroups = ChannelGroup::all()->keyBy('id');

            foreach ($allChannelGroups as $channelGroupId => $channelGroup) {
                $member->userChannels()->create([
                    'channel_group_id' => $channelGroupId,
                    'fee_percent'      => $feePercent = data_get($userChannelFeePercents, $channelGroupId, null),
                    'min_amount'       => null,
                    'max_amount'       => null,
                    'status'           => is_null($feePercent) ? Channel::STATUS_DISABLE : Channel::STATUS_ENABLE,
                    'floating_enable'  => false,
                    'withdraw_min_amount' => $request->input('withdraw_min_amount', null),
                    'withdraw_max_amount' => $request->input('withdraw_max_amount', null),
                    'agency_withdraw_min_amount' => $request->input('agency_withdraw_min_amount', null),
                    'agency_withdraw_max_amount' => $request->input('agency_withdraw_max_amount', null)

                ]);
            }

            return $member;
        });

        return UserResource::make($member->load('wallet', 'userChannels'))
            ->withCredentials(['password' => $password, 'google2fa_secret' => $google2faSecret, 'secret_key' => $secretKey]);
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

    public function update(UpdateUserRequest $request, UserModel $member)
    {
        $this->abortIfMemberNotDirectChildren($member);

        DB::transaction(function () use ($member, $request) {
            $member->update($request->only([
                'name', 'phone', 'contact', 'withdraw_enable', 'agency_withdraw_enable'
            ]));

            foreach ([
                'withdraw_fee', 'withdraw_fee_percent', 'additional_withdraw_fee',
                'agency_withdraw_fee', 'agency_withdraw_fee_dollar', 'additional_agency_withdraw_fee',
                'withdraw_min_amount', 'withdraw_max_amount',
                'agency_withdraw_min_amount', 'agency_withdraw_max_amount'
            ] as $walletAttribute) {
                if ($request->has($walletAttribute)) {
                    $member->wallet->$walletAttribute = $request->input($walletAttribute, $member->wallet->$walletAttribute);
                }
            }
        });

        return UserResource::make($member->load('wallet', 'parent'));
    }
}
