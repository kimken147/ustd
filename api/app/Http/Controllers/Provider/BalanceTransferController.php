<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Model\User;
use App\Utils\BCMathUtil;
use App\Utils\WalletUtil;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BalanceTransferController extends Controller
{

    public function store(
        Request $request,
        BCMathUtil $bcMath,
        WalletUtil $wallet
    ) {
        abort_if(
            !auth()->user()->balance_transfer_enable,
            Response::HTTP_BAD_REQUEST,
            '站内转点功能已停用'
        );

        $this->validate($request, [
            'user_id' => 'int',
            'amount'  => 'numeric|min:1',
            'note'    => 'required|string|max:255',
        ]);

        /** @var User $user */
        $user = User::where('role', User::ROLE_PROVIDER)->find($request->user_id);

        abort_if(
            !$user,
            Response::HTTP_BAD_REQUEST,
            '查无使用者'
        );

        abort_if(
            $user->is(auth()->user()),
            Response::HTTP_BAD_REQUEST,
            '禁止转点给自己'
        );

        DB::transaction(function () use ($user, $wallet, $bcMath, $request) {
            abort_if(
                $bcMath->lt(auth()->user()->wallet->available_balance, $bcMath->abs($request->amount)),
                Response::HTTP_BAD_REQUEST,
                __('wallet.InsufficientAvailableBalance')
            );

            $updatedRows = $wallet->transfer(
                auth()->user()->wallet, // from
                $user->wallet, // to
                $request->input('amount', 0), // amount
                auth()->user(), // operator
                $request->note
            );

            abort_if(
                $updatedRows !== 2, // from + to = 2
                Response::HTTP_CONFLICT,
                __('common.Wallet update conflicts, please try again later')
            );
        });

        return response()->json(null, Response::HTTP_CREATED);
    }
}
