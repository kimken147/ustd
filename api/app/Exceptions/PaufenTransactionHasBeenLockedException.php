<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class PaufenTransactionHasBeenLockedException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '交易已锁定，请联络客服');
    }
}
