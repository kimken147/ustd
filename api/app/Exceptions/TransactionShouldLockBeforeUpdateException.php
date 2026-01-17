<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class TransactionShouldLockBeforeUpdateException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '请先锁定');
    }
}
