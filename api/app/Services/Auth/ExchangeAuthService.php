<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExchangeAuthService extends BaseAuthService
{
    protected function getAllowedRoles(): array
    {
        return [User::ROLE_PROVIDER];
    }

    protected function validateUserBeforeAttempt(User $user, Request $request): void
    {
        abort_if(
            !$user->exchange_mode_enable,
            Response::HTTP_BAD_REQUEST,
            '登入失败'
        );
    }
}
