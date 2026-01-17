<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChildTransactionController extends Controller
{
    public function store(Transaction $transaction, TransactionUtil $transactionUtil, Request $request)
    {
        $this->validate($request, [
            'amount' => 'required|numeric',
        ]);

        if ($request->has('_search1')) {
            $search1 = $request->input('_search1');
            abort_if(Transaction::where('_search1', $search1)->exists(), Response::HTTP_BAD_REQUEST, "{$search1}已重複");
        }

        $childTransaction = $transactionUtil->markPaufenTransactionAsPartialSuccess($transaction, $request->amount, auth()->user()->realUser());

        if ($request->has('_search1')) {
            $childTransaction->update(['_search1' => $request->input('_search1')]);
        }

        return \App\Http\Resources\Admin\Transaction::make($childTransaction->load('from', 'to'));
    }
}
