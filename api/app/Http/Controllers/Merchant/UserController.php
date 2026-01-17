<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserCollection;
use App\Http\Resources\User as UserResource;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Utils\NotificationUtil;
use App\Utils\WhitelistedIpManager;
use App\Utils\UserUtil;
use Illuminate\Http\Response;
use Illuminate\Http\Request;


class UserController extends Controller
{
    public function descendants()
    {
        $users = User::descendantsAndSelf(auth()->id());

        return UserCollection::make($users);
    }

    public function resetGoogle2faSecret(
        NotificationUtil $notificationUtil,
        WhitelistedIpManager $whitelistedIpManager,
        Request $request
    ) {

        $userUtil = app(UserUtil::class);
        auth()->user()->update([
            'google2fa_secret' => $google2faSecret = $userUtil->generateGoogle2faSecret(),
        ]);

        $notificationUtil->notifyAdminResetGoogle2faSecret(auth()->user()->realUser(), auth()->user(),
            $whitelistedIpManager->extractIpFromRequest($request));

        return UserResource::make(auth()->user()->load('wallet', 'parent'))
            ->withCredentials(['google2fa_secret' => $google2faSecret])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(UpdateUserRequest $request) {
        auth()->user()->update($request->only([
            'google2fa_enable', 'withdraw_google2fa_enable'
        ]));
        return UserResource::make(auth()->user()->load('wallet', 'parent'));
    }
}
