<?php

namespace App\Http\Requests;

use App\Models\WalletHistory;
use DateTimeInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListWalletHistoryRequest extends FormRequest
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
            'started_at' => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at'   => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'type'       => [
                'nullable',
                'array',
            ]
        ];
    }
}
