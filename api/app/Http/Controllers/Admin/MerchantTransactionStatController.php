<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\MerchantTransactionStatCollection;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kalnoy\Nestedset\NestedSet;
use Illuminate\Http\Response;
class MerchantTransactionStatController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, [
            'parent_id' => 'nullable',
        ]);

        $users = User::where('parent_id', $request->parent_id)
            ->where('role', User::ROLE_MERCHANT)
            ->get(['id', 'name', NestedSet::PARENT_ID, NestedSet::LFT, NestedSet::RGT]);

        $selfTransactionStats = DB::table('transactions')
            ->whereIn('to_id', $users->pluck('id'))
            ->where('confirmed_at', '>=', Carbon::yesterday())
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->groupBy(DB::raw('CONCAT(to_id, "_", DATE(confirmed_at))'))
            ->get([
                DB::raw('CONCAT(to_id, "_", DATE(confirmed_at)) AS user_id_date'),
                DB::raw('SUM(amount) AS total'),
            ])
            ->pluck('total', 'user_id_date');

        $today = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        $users = $users->map(function (User $user) use ($selfTransactionStats, $today, $yesterday) {
            $user->setAttribute('yesterday_self_total', data_get($selfTransactionStats, "{$user->getKey()}_$yesterday", '0.00'));
            $user->setAttribute('today_self_total', data_get($selfTransactionStats, "{$user->getKey()}_$today", '0.00'));

            $descendantIds = $user->descendants()->pluck('id');
            $descendantsAndSelf = $descendantIds->merge($user->getKey());

            $descendantTransactionStats = DB::table('transactions')
                ->whereIn('to_id', $descendantIds)
                ->where('confirmed_at', '>=', Carbon::yesterday())
                ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
                ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                ->groupBy(DB::raw('DATE(confirmed_at)'))
                ->get([
                    DB::raw('DATE(confirmed_at) AS date'),
                    DB::raw('SUM(amount) AS total'),
                ])
                ->pluck('total', 'date');

            $balanceTotal = data_get(DB::table('wallets')
                ->whereIn('user_id', $descendantsAndSelf)
                ->get([
                    DB::raw('SUM(balance) AS balance_total'),
                ])
                ->first(), 'balance_total', '0');

            $user->setAttribute('yesterday_descendants_total', data_get($descendantTransactionStats, $yesterday, '0.00'));
            $user->setAttribute('today_descendants_total', data_get($descendantTransactionStats, $today, '0.00'));
            $user->setAttribute('descendants_total', $descendantIds->count());
            $user->setAttribute('balance_total', $balanceTotal);

            return $user;
        });

        return MerchantTransactionStatCollection::make($users);
    }
}
