<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class InvalidChildWithdrawAmountException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '子订单金额需大于零');
    }
}
