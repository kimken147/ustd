<?php


namespace App\Utils;


use Exception;
use Illuminate\Http\Response;

class InsufficientAvailableBalance extends Exception
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, __('wallet.InsufficientAvailableBalance'));
    }
}
