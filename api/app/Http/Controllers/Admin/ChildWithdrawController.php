<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Utils\BankCardTransferObject;
use App\Utils\BCMathUtil;
use App\Utils\TransactionFactory;
use App\Utils\TransactionUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChildWithdrawController extends Controller
{

    public function store(
        Transaction $withdraw,
        Request $request,
        TransactionUtil $transactionUtil
    ) {
        $this->validate($request, [
            'child_withdraws'          => 'required|array',
            'child_withdraws.*.type'   => [
                'required', Rule::in([Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])
            ],
            'child_withdraws.*.amount' => ['required', 'numeric'],
            'child_withdraws.*.to_id'  => ['nullable']
        ]);

        $transactionUtil->separateWithdraw($withdraw, collect($request->input('child_withdraws')));

        return response()->json(null, Response::HTTP_CREATED);
    }
}
