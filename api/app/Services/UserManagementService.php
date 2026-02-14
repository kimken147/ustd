<?php

namespace App\Services;

use App\Http\Resources\User as UserResource;
use App\Models\User;
use App\Models\UserChannel;
use App\Utils\BCMathUtil;
use App\Utils\NotificationUtil;
use App\Utils\UserUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserManagementService
{
    private UserUtil $user;

    public function __construct(UserUtil $user)
    {
        $this->user = $user;
    }

    public function abortIfUsernameNotAlnum(string $username): void
    {
        abort_if(
            !ctype_alnum($username),
            Response::HTTP_BAD_REQUEST,
            __('common.Username can only be alphanumeric')
        );
    }

    public function abortIfUsernameAlreadyExists(string $username): void
    {
        abort_if(
            $this->user->usernameAlreadyExists($username),
            Response::HTTP_BAD_REQUEST,
            __('common.Duplicate username')
        );
    }

    public function resetGoogle2faSecret(
        User $user,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request
    ) {
        $google2faSecret = DB::transaction(function () use (
            $user,
            $notificationUtil,
            $whitelistedIpManager,
            $request
        ) {
            $user->update([
                'google2fa_secret' => $google2faSecret = $this->user->generateGoogle2faSecret(),
            ]);

            $notificationUtil->notifyAdminResetGoogle2faSecret(
                auth()->user()->realUser(),
                $user,
                $whitelistedIpManager->extractIpFromRequest($request)
            );

            return $google2faSecret;
        });

        return UserResource::make($user->load('wallet', 'parent'))
            ->withCredentials(['google2fa_secret' => $google2faSecret])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * @param bool $allowCustomPassword Provider 傳 true（接受 request password），Merchant 傳 false（總是生成）
     */
    public function resetPassword(
        User $user,
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request,
        bool $allowCustomPassword = false
    ) {
        $password = DB::transaction(function () use ($user, $notificationUtil, $whitelistedIpManager, $request, $allowCustomPassword) {
            $password = $allowCustomPassword
                ? $request->input('password', $this->user->generatePassword())
                : $this->user->generatePassword();

            $user->update([
                'password' => Hash::make($password),
            ]);

            $notificationUtil->notifyAdminResetPassword(
                auth()->user()->realUser(),
                $user,
                $whitelistedIpManager->extractIpFromRequest($request)
            );

            return $password;
        });

        return UserResource::make($user->load('wallet', 'parent'))
            ->withCredentials(['password' => $password])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * 驗證 channel fee 設定
     *
     * @param string $comparison 'gt' (Provider: 下級不能高於上級) 或 'lt' (Merchant: 下級不能低於上級)
     */
    public function validateChannelFees(
        User $agent,
        array $userChannels,
        BCMathUtil $bcMath,
        string $comparison = 'gt'
    ): void {
        foreach ($userChannels as $userChannel) {
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
                $bcMath->$comparison($userChannel['fee_percent'], $agentUserChannel->fee_percent),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );
        }
    }
}
