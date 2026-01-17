<?php

namespace App\Http\Resources;

use App\Http\Resources\Admin\PermissionCollection;
use App\Http\Resources\Admin\WhitelistedIpCollection;
use App\Model\Permission;
use App\Model\FeatureToggle;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
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
                'id'       => $this->getKey(),
                'name'     => $this->name,
                'username' => $this->username,
            ];
        }

        $data = [
            'id'                            => $this->id,
            'last_login_ipv4'               => $this->last_login_ipv4,
            'role'                          => $this->role,
            'status'                        => $this->status,
            'agent_enable'                  => $this->agent_enable,
            'google2fa_enable'              => $this->google2fa_enable,
            'deposit_enable'                => $this->when(
                $this->role === \App\Model\User::ROLE_PROVIDER,
                $this->deposit_enable
            ),
            'paufen_deposit_enable'         => $this->paufen_deposit_enable,
            'withdraw_review_enable'        => $this->withdraw_review_enable,
            'withdraw_enable'               => $this->withdraw_enable,
            'withdraw_profit_enable'        => $this->withdraw_profit_enable,
            'withdraw_google2fa_enable'     => $this->withdraw_google2fa_enable,
            'paufen_withdraw_enable'        => $this->paufen_withdraw_enable,
            'agency_withdraw_enable'        => $this->agency_withdraw_enable,
            'paufen_agency_withdraw_enable' => $this->paufen_agency_withdraw_enable,
            'transaction_enable'            => $this->transaction_enable,
            'third_channel_enable'          => $this->third_channel_enable,
            'credit_mode_enable'            => $this->creditModeEnabled(),
            'deposit_mode_enable'           => $this->depositModeEnabled(),
            'balance_transfer_enable'       => $this->balance_transfer_enable,
            'ready_for_matching'            => $this->ready_for_matching,
            'account_mode'                  => $this->account_mode,
            'name'                          => $this->name,
            'username'                      => $this->username,
            'last_login_at'                 => ($this->last_login_at != "") ? optional($this->last_login_at)->toIso8601String() : "",
            'withdraw_fee'                  => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_fee;
            }),
            'withdraw_fee_percent'          => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_fee_percent;
            }),
            'withdraw_profit_fee'           => $this->whenLoaded('wallet', function () {
                return $this->wallet->withdraw_profit_fee;
            }),
            'additional_withdraw_fee'       => $this->whenLoaded('wallet', function () {
                return $this->wallet->additional_withdraw_fee;
            }),
            'agency_withdraw_fee'           => $this->whenLoaded('wallet', function () {
                return $this->wallet->agency_withdraw_fee;
            }),
            'agency_withdraw_fee_dollar'    => $this->whenLoaded('wallet', function () {
                return $this->wallet->agency_withdraw_fee_dollar;
            }),
            'additional_agency_withdraw_fee' => $this->whenLoaded('wallet', function () {
                return $this->wallet->additional_agency_withdraw_fee;
            }),
            'transactions_today'            => $this->whenLoaded('todaySuccessPaufenTransactions', function () {
                return AmountDisplayTransformer::transform($this->todaySuccessPaufenTransactions->sum('amount') ?? '0.00');
            }),
            'withdraw_today'                => $this->whenLoaded('todaySuccessWithdraws', function () {
                return AmountDisplayTransformer::transform($this->todaySuccessWithdraws->sum('amount') ?? '0.00');
            }),
            'subtract_today'                => $this->whenLoaded('todaySuccessPaufenTransactions', function () {
                return AmountDisplayTransformer::transform(($this->todaySuccessPaufenTransactions->sum('amount') - $this->todaySuccessWithdraws->sum('amount')) ?? 0.00);
            }),
            'wallet'                        => Wallet::make($this->whenLoaded('wallet')),
            'balance_limit'                 => $this->balance_limit,
            'agent'                         => User::make($this->whenLoaded('parent'))->asAgent(),
            'root_agent'                    => $this->whenLoaded('rootParents', function () {
                return User::make($this->rootParents->first())->asAgent();
            }),
            'message_enabled'               => false,
            'user_channels'                 => UserChannelCollection::make($this->whenLoaded('userChannels')),
            'phone'                         => $this->phone,
            'contact'                       => $this->contact,
            'usdt_rate'                     => $this->usdt_rate,
            'permissions'                   => PermissionCollection::make($this->whenLoaded('permissions')),
            'whitelisted_ips'               => WhitelistedIpCollection::make($this->whenLoaded('whitelistedIps')),
            'ui_permissions'                => $this->whenLoaded('permissions', function () {
                /** @var Collection $permissions */
                $permissions = $this->permissions->mapWithKeys(function ($item) {
                    return [$item->getKey() => $item];
                });

                return [
                    'manage_whitelisted_ip'                   => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_WHITELISTED_IP),
                    'manage_time_limit_bank'                  => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_TIME_LIMIT_BANK),
                    'manage_matching_deposit_reward'          => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_MATCHING_DEPOSIT_REWARD),
                    'manage_transaction_reward'               => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_TRANSACTION_REWARD),
                    'manage_fill_in_order'                    => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_CREATE_FILL_IN_ORDER),
                    'manage_provider_whitelisted_ip'          => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_PROVIDER_WHITELISTED_IP),
                    'manage_merchant_login_whitelisted_ip'    => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_MERCHANT_LOGIN_WHITELISTED_IP),
                    'manage_merchant_api_whitelisted_ip'      => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_MERCHANT_API_WHITELISTED_IP),
                    'manage_merchant_blocklist'               => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_BANNED_IP),
                    'manage_sensitive_data'                   => $this->realUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_SHOW_SENSITIVE_DATA),
                    'manage_merchant_matching_deposit_groups' => $this->mainUser()->role === \App\Model\User::ROLE_ADMIN,
                    'manage_merchant_transaction_groups'      => $this->mainUser()->role === \App\Model\User::ROLE_ADMIN,
                    'manage_merchant_third_channel'           => $this->mainUser()->role === \App\Model\User::ROLE_ADMIN || $permissions->has(Permission::ADMIN_MANAGE_MERCHANT_THIRD_CHANNEL),
                ];
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
            'currency' => $this->currency,
            'cancel_order_enable'           => $this->cancel_order_enable,
            'exchange_mode_enable'          => $this->exchange_mode_enable,
            'control_downline'              => $this->control_downline,
            'control_downlines'             => UserCollection::make($this->whenLoaded('controlDownlines')),
            'downlines'                     => UserCollection::make($this->whenLoaded('descendants')),
            'created_at'                    => ($this->created_at != "") ? $this->created_at->toIso8601String() : "",
            'tags' => $this->whenLoaded('tags', function () {
                return $this->tags->makeHidden('pivot')->toArray();
            }),
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
