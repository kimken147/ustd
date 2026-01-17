<?php

namespace App\Http\Controllers;

use App\Services\Transaction\TransactionService;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    private TransactionService $transactionService;

    public function __construct(TransactionService $transactionService)
    {
        $this->transactionService = $transactionService;
    }

    public function show(Request $request, string $orderNumber)
    {
        $transaction = $this->transactionService->findOne($orderNumber);

        return response()->json($transaction->only(["amount", "channel_code", "order_number", "status", "type", "from_channel_account", "to_channel_account", "created_at", "confirmed_at", "_search1"]));
    }
}
