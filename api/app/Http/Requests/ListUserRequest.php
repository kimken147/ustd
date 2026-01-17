<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListUserRequest extends FormRequest
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
            'ids'                => 'nullable|array',
            'no_paginate'        => 'boolean',
            'status'             => 'nullable|boolean',
            'google2fa_enable'   => 'nullable|boolean',
            'deposit_enable'     => 'nullable|boolean',
            'withdraw_enable'    => 'nullable|boolean',
            'withdraw_profit_enable' => 'nullable|boolean',
            'transaction_enable' => 'nullable|boolean',
            'agent_enable'       => 'nullable|boolean',
            'root_only'          => 'nullable|boolean',
            'name_or_username'   => 'nullable|string',
            'ipv4'               => 'nullable|ipv4',
            'merchant_name_or_username' => 'nullable|array',
            'provider_name_or_username' => 'nullable|string',
            'tag_ids' => 'sometimes|array',
        ];
    }
}
