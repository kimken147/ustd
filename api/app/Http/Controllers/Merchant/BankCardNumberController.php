<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Utils\BankCardNumberRecognizerTrait;
use Illuminate\Http\Response;

class BankCardNumberController extends Controller
{

    use BankCardNumberRecognizerTrait;

    public function show($bankCardNumber)
    {
        abort_if(
            empty($bankName = $this->bankName($bankCardNumber)),
            Response::HTTP_NOT_FOUND,
            __('bank-card.Unable to resolve bank name from card number')
        );

        return response()->json([
            'data' => [
                'bank_name' => $bankName,
            ],
        ]);
    }
}
