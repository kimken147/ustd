<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\UsdtDepositMonitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CashierTxHashController extends Controller
{
    public function __invoke(Request $request, string $systemOrderNumber): JsonResponse
    {
        $this->validate($request, [
            'tx_hash' => 'required|string|max:100',
        ]);

        $transaction = Transaction::where('system_order_number', $systemOrderNumber)
            ->where('status', Transaction::STATUS_PAYING)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        abort_if(!$transaction, Response::HTTP_NOT_FOUND, '查无此订单');

        $monitor = UsdtDepositMonitor::where('transaction_id', $transaction->id)
            ->where('status', UsdtDepositMonitor::STATUS_PENDING)
            ->first();

        abort_if(!$monitor, Response::HTTP_NOT_FOUND, '查无监控记录');

        $monitor->update([
            'user_tx_hash' => $request->input('tx_hash'),
        ]);

        return response()->json(['message' => '提交成功']);
    }
}
