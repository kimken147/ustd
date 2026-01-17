<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdminCreateUserChannelRequest extends FormRequest
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
            'channel_code'    => 'required',
            'min_amount'      => 'required|numeric',
            'max_amount'      => 'required|gte:min_amount|numeric',
            'fee_percent'     => 'required|numeric', // 簡易防呆，手續費不可能超過 10%
            'floating_enable' => 'boolean',
        ];
    }
}
