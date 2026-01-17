<?php

namespace App\Http\Resources\Merchant;

use App\Http\Resources\UserChannelCollection;
use App\Http\Resources\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PragmaRX\Google2FALaravel\Google2FA;

class SubAccount extends JsonResource
{

    /**
     * @var array
     */
    private $withCredentials = [];

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $parent = $this->parent->load('userChannels');
        $wallet = $parent->wallet;

        $data = [
            'id'                => $this->id,
            'last_login_ipv4'   => $this->last_login_ipv4,
            'role'              => $this->role,
            'name'              => $this->name,
            'username'          => $this->username,
            'last_login_at'     => optional($this->last_login_at)->toIso8601String(),
            'status'            => $this->status,
            'google2fa_enable'  => $this->google2fa_enable,
            'withdraw_fee' => $wallet->withdraw_fee,
            'withdraw_fee_percent' => $wallet->withdraw_fee_percent,
            'additional_withdraw_fee' => $wallet->additional_withdraw_fee,
            'agency_withdraw_fee' => $wallet->agency_withdraw_fee,
            'agency_withdraw_fee_dollar' => $wallet->agency_withdraw_fee_dollar,
            'additional_agency_withdraw_fee' => $wallet->additional_agency_withdraw_fee,
            'wallet' => Wallet::make($wallet),
            'user_channels'  => UserChannelCollection::make($parent->userChannels),
            'parent' => User::make($parent)
        ];

        foreach ($this->withCredentials as $credentialKey => $credential) {
            $data[$credentialKey] = $credential;

            if ($credentialKey === 'google2fa_secret') {
                $data['google2fa_qrcode'] = app(Google2FA::class)
                    ->getQRCodeInline('', $this->name, $this->google2fa_secret);
            }
        }

        return $data;
    }

    public function withCredentials(array $credentials)
    {
        $this->withCredentials = $credentials;

        return $this;
    }
}
