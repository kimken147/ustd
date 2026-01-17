<?php


namespace App\Exceptions;


use Illuminate\Http\Response;
use RuntimeException;

/**
 * 子訂單的狀態要全部一樣，不能部分成功部分失敗
 */
class DifferentChildWithdrawStatusException extends RuntimeException
{
    public function render()
    {
        abort(Response::HTTP_BAD_REQUEST, '子订单状态需统一，请重新确认');
    }
}
