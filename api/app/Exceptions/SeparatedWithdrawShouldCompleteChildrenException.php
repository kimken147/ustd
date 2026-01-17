<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

/**
 * 已拆單的主訂單不可直接做修改，需完成所有子訂單
 */
class SeparatedWithdrawShouldCompleteChildrenException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '订单已拆单，请先完成所有子订单');
    }
}
