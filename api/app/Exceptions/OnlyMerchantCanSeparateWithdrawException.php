<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class OnlyMerchantCanSeparateWithdrawException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '拆单仅限商户提现');
    }
}
