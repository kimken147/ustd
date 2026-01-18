<?php

namespace App\Services\Auth;

use App\Models\User;

class MerchantAuthService extends BaseAuthService
{
    protected function getAllowedRoles(): array
    {
        return [User::ROLE_MERCHANT, User::ROLE_MERCHANT_SUB_ACCOUNT];
    }

    protected function getPasswordChangeUser(): User
    {
        return auth()->user()->realUser();
    }
}
