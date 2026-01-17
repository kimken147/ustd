<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class TransactionRefundedException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, __('common.Transaction already refunded'));
    }
}
