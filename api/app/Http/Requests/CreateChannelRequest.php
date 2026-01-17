<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateChannelRequest extends FormRequest
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
            'channel_code' => 'required|in:alipay_qrcode,alipay_wap,wepay_qrcode,wepay_wap,bank_card,unionpay,yfb_qrcode',
            'max_amount' => 'required|integer|gte:min_amount',
            'min_amount' => 'required|integer',
            'floating_eanble' => 'boolean'
        ];
    }
}
