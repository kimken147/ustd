<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class ChildWithdrawCannotSeparateException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '子订单不可拆单');
    }
}
