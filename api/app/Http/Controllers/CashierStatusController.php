<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CashierStatusController extends Controller
{
    public function __invoke(string $id): JsonResponse
    {
        $transaction = Transaction::where('system_order_number', $id)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        abort_if(!$transaction, Response::HTTP_NOT_FOUND, '查无此订单');

        $statusMap = [
            Transaction::STATUS_MATCHING => 'matching',
            Transaction::STATUS_PAYING => 'paying',
            Transaction::STATUS_SUCCESS => 'success',
            Transaction::STATUS_MANUAL_SUCCESS => 'success',
            Transaction::STATUS_MATCHING_TIMED_OUT => 'timed_out',
            Transaction::STATUS_PAYING_TIMED_OUT => 'timed_out',
            Transaction::STATUS_FAILED => 'failed',
        ];

        return response()->json([
            'status' => $statusMap[$transaction->status] ?? 'unknown',
        ]);
    }
}
