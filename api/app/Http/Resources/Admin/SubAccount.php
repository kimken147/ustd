<?php

namespace App\Http\Resources\Admin;

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
        $data = [
            'id'                => $this->id,
            'last_login_ipv4'   => $this->last_login_ipv4,
            'role'              => $this->role,
            'name'              => $this->name,
            'username'          => $this->username,
            'last_login_at'     => optional($this->last_login_at)->toIso8601String(),
            'status'            => $this->status,
            'google2fa_enable'  => $this->google2fa_enable,
            'permissions'       => PermissionCollection::make($this->permissions),
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
