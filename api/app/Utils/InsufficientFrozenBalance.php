<?php


namespace App\Utils;


use Exception;
use Illuminate\Http\Response;

class InsufficientFrozenBalance extends Exception
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '冻结余额不足');
    }
}
