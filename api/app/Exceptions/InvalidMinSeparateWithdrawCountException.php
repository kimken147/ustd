<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class InvalidMinSeparateWithdrawCountException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '拆单至少两单');
    }
}
