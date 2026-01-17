<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

class InvalidStatusException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, __('common.Invalid Status'));
    }
}
