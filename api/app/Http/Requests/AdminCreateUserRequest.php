<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminCreateUserRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'agent_enable'                     => 'boolean',
            'google2fa_enable'                 => 'boolean',
            'deposit_enable'                   => 'nullable|boolean',
            'paufen_deposit_enable'            => 'nullable|boolean',
            'withdraw_enable'                  => 'nullable|boolean',
            'withdraw_profit_enable'           => 'nullable|boolean',
            'paufen_withdraw_enable'           => 'nullable|boolean',
            'transaction_enable'               => 'nullable|boolean',
            'third_channel_enable'             => 'nullable|boolean',
            'name'                             => 'required|max:20',
            'username'                         => 'required|max:20',
            'user_channels'                    => 'array',
            'user_channels.*.channel_group_id' => 'required_with:user_channels',
            'user_channels.*.fee_percent'      => 'nullable|numeric', // 簡易防呆，手續費不可能超過 10%
            'withdraw_fee'                     => 'nullable|numeric|min:0',
            'withdraw_profit_fee'              => 'nullable|numeric|min:0',
            'agency_withdraw_fee'              => 'nullable|numeric|min:0',
            'agency_withdraw_fee_dollar'       => 'nullable|numeric|min:0',
            'phone'                            => 'nullable|string|max:50',
            'contact'                          => 'nullable|string|max:255',
        ];
    }
}
