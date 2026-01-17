<?php

namespace App\Http\Requests;

use App\Model\Channel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserChannelRequest extends FormRequest
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
            'status'           => [Rule::in([Channel::STATUS_DISABLE, Channel::STATUS_ENABLE])],
            'fee_percent'      => 'nullable|numeric|max:20', // 簡易防呆，手續費不可能超過 10%
            'floating_enable'  => 'boolean',
            'real_name_enable' => 'boolean',
        ];
    }
}
