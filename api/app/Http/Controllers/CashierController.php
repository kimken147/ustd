<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\Transaction\CreateTransactionService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CashierController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $transaction = Transaction::where('system_order_number', $id)
            ->where('created_at', '>=', now()->subDay())
            ->first();

        abort_if(!$transaction, Response::HTTP_NOT_FOUND, '查无此订单');

        $result = app(CreateTransactionService::class)->buildResultForExistingTransaction($transaction);

        return app(CreateTransactionController::class)->renderForCashier($result);
    }
}
