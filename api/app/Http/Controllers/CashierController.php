<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\Transaction;

class CashierController extends Controller
{
    public function __invoke(Request $request, $id)
    {
        $transaction = Transaction::where('system_order_number', $id)->where('created_at', '>=', now()->subDay())->first();

        abort_if(!$transaction, Response::HTTP_NOT_FOUND, '查无此订单');

        $query = json_decode($transaction->to_channel_account['query'], true);
        $request->request->add($query);

        return app()->call('\App\Http\Controllers\CreateTransactionController@__invoke', ['request' => $request]);
    }
}
