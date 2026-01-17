<?php

namespace App\Utils;

use App\Models\User as UserModel;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class UserUtil
{

    /**
     * @var Google2FA
     */
    private $google2FA;

    public function __construct(Google2FA $google2FA = null)
    {
        $this->google2FA = $google2FA;
    }

    public function generatePassword()
    {
        return Str::random(8);
    }

    public function generateSecretKey()
    {
        return Str::random(32);
    }

    public function generateGoogle2faSecret()
    {
        return $this->google2FA->generateSecretKey();
    }

    public function findProviderWithId($id)
    {
        if (empty($id)) {
            return null;
        }

        return UserModel::ofRole(UserModel::ROLE_PROVIDER)->find($id);
    }

    public function findMerchantWithId($id)
    {
        if (empty($id)) {
            return null;
        }

        return UserModel::ofRole(UserModel::ROLE_MERCHANT)->find($id);
    }

    public function usernameAlreadyExists(string $username)
    {
        return UserModel::where('username', $username)->withTrashed()->exists();
    }

    public function checkLowerAgentIsNotDelete($parent_id)
    {
        return UserModel::where('parent_id', $parent_id)->count() > 0;
    }
}
