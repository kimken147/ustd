<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
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
            'name'                       => 'max:20',
            'username'                   => 'max:20',
            'balance_delta'              => 'numeric',
            'note'                       => 'nullable|string|max:255',
            'phone'                      => 'nullable|string|max:50',
            'contact'                    => 'nullable|string|max:255',
            'paufen_deposit_enable'      => 'boolean',
            'paufen_withdraw_enable'     => 'boolean',
            'withdraw_fee'               => 'numeric|min:0',
            'withdraw_profit_fee'        => 'numeric|min:0',
            'agency_withdraw_fee'        => 'nullable|numeric|min:0',
            'agency_withdraw_fee_dollar' => 'nullable|numeric|min:0',
            'withdraw_min_amount'        => 'numeric|nullable',
            'withdraw_max_amount'        => 'numeric|nullable',
            'withdraw_profit_min_amount' => 'numeric|nullable',
            'withdraw_profit_max_amount' => 'numeric|nullable',
            'agency_withdraw_min_amount' => 'numeric|nullable',
            'agency_withdraw_max_amount' => 'numeric|nullable',
            'ready_for_matching'         => 'boolean',
            'tag_ids' => 'sometimes|array',
            'tag_ids.*' => 'exists:tags,id'
        ];
    }
}
