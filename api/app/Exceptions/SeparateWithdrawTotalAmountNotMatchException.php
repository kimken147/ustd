<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class SeparateWithdrawTotalAmountNotMatchException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '子订单金额总和与原订单金额不符，请调整子订单金额');
    }
}
