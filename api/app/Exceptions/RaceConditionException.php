<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class RaceConditionException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, __('common.Conflict! Please try again later'));
    }
}
