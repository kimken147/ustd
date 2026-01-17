<?php

namespace App\Http\Resources\Merchant;

use App\Http\Resources\UserChannelCollection;
use App\Http\Resources\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PragmaRX\Google2FALaravel\Google2FA;

class User extends JsonResource
{

    /**
     * @var bool
     */
    private $asAgent = false;

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
        if ($this->asAgent) {
            return [
                'id'   => $this->getKey(),
                'name' => $this->name,
            ];
        }

        $data = [
            'id'                        => $this->id,
            'last_login_ipv4'           => $this->last_login_ipv4,
            'role'                      => $this->role,
            'status'                    => $this->status,
            'agent_enable'              => $this->agent_enable,
            'google2fa_enable'          => $this->google2fa_enable,
            'withdraw_enable'           => $this->withdraw_enable,
            'withdraw_google2fa_enable' => $this->withdraw_google2fa_enable,
            'agency_withdraw_enable'    => $this->agency_withdraw_enable,
            'transaction_enable'        => $this->transaction_enable,
            'credit_mode_enable'        => $this->creditModeEnabled(),
            'deposit_mode_enable'       => $this->depositModeEnabled(),
            'account_mode'              => $this->account_mode,
            'name'                      => $this->name,
            'username'                  => $this->username,
            'last_login_at'             => optional($this->last_login_at)->toIso8601String(),
            'withdraw_fee'              => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_fee;
            }),
            'withdraw_fee_percent'      => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_fee_percent;
            }),
            'additional_withdraw_fee'   => $this->whenLoaded('wallet', function () {
                return $this->wallet->additional_withdraw_fee;
            }),
            'agency_withdraw_fee'       => $this->whenLoaded('wallet', function () {
                return $this->wallet->agency_withdraw_fee;
            }),
            'agency_withdraw_fee_dollar' => $this->whenLoaded('wallet', function () {
                return $this->wallet->agency_withdraw_fee_dollar;
            }),
            'additional_agency_withdraw_fee' => $this->whenLoaded('wallet', function () {
                return $this->wallet->additional_agency_withdraw_fee;
            }),
            'withdraw_min_amount'           => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_min_amount;
            }),
            'withdraw_max_amount'           => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_max_amount;
            }),
            'withdraw_profit_min_amount'           => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_profit_min_amount;
            }),
            'withdraw_profit_max_amount'           => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_profit_max_amount;
            }),
            'agency_withdraw_min_amount'    => $this->whenLoaded('wallet', function () {
                return $this->wallet->agency_withdraw_min_amount;
            }),
            'agency_withdraw_max_amount'    => $this->whenLoaded('wallet', function () {
                return $this->wallet->agency_withdraw_max_amount;
            }),
            'wallet'                    => Wallet::make($this->whenLoaded('wallet')),
            'agent'                     => User::make($this->whenLoaded('parent'))->asAgent(),
            'user_channels'             => UserChannelCollection::make($this->whenLoaded('userChannels')),
            'phone'                     => $this->phone,
            'contact'                   => $this->contact,
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

    public function asAgent()
    {
        $this->asAgent = true;

        return $this;
    }

    public function withCredentials(array $credentials)
    {
        $this->withCredentials = $credentials;

        return $this;
    }
}
