<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
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
            'name'                             => 'required|max:20',
            'username'                         => 'required|max:20',
            'user_channels'                    => 'array',
            'user_channels.*.channel_group_id' => 'required_with:user_channels|int',
            'user_channels.*.fee_percent'      => 'nullable|numeric',
            'phone'                            => 'nullable|string|max:50',
            'contact'                          => 'nullable|string|max:255',
        ];
    }
}
