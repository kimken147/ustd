<?php


namespace App\Utils;


use Exception;
use Illuminate\Http\Response;

class InsufficientProfit extends Exception
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '红利不足');
    }
}
