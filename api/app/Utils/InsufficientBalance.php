<?php


namespace App\Utils;


use Exception;
use Illuminate\Http\Response;

class InsufficientBalance extends Exception
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '余额不足');
    }
}
