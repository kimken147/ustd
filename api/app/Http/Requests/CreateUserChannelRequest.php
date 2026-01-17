<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserChannelRequest extends FormRequest
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
            'user_channel_id' => 'required',
            'fee_percent'     => 'required|numeric', // 簡易防呆，手續費不可能超過 10%
            'floating_enable' => 'boolean',
        ];
    }
}
