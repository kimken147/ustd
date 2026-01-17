<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class TransactionLockerNotYouException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '非锁定人无法操作');
    }
}
