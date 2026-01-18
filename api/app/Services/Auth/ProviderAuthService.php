<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Arr;
use Stevebauman\Location\Facades\Location;

class ProviderAuthService extends BaseAuthService
{
    protected function getAllowedRoles(): array
    {
        return [User::ROLE_PROVIDER];
    }

    protected function updateLoginRecord(?string $token): void
    {
        $ip = Arr::last(request()->ips());
        $city = str_replace('\'', ' ', optional(Location::get($ip))->cityName);

        auth($this->getGuard())->user()->update([
            'last_login_at'   => now(),
            'last_login_ipv4' => $ip,
            'last_login_city' => $city,
        ]);
    }
}
