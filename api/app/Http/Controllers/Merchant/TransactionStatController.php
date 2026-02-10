<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\TransactionStatCollection;
use App\Utils\DateRangeValidator;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Kalnoy\Nestedset\NestedSet;

class TransactionStatController extends Controller
{

    public function index(Request $request)
    {
        $parentId = auth()->user()->getKey();

        $this->validate($request, [
            'parent_id' => 'nullable|in:'.$parentId,
        ]);

        if ($request->parent_id) {
            $users = User::where('parent_id', $request->parent_id)
                ->where('role', User::ROLE_MERCHANT)
                ->get(['id', 'name', 'username', NestedSet::PARENT_ID, NestedSet::LFT, NestedSet::RGT]);
        } else {
            $users = User::where('id', $parentId)->get(['id', 'name', 'username', NestedSet::PARENT_ID, NestedSet::LFT, NestedSet::RGT]);
        }

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

            $user->setAttribute('yesterday_descendants_total', data_get($descendantTransactionStats, $yesterday, '0.00'));
            $user->setAttribute('today_descendants_total', data_get($descendantTransactionStats, $today, '0.00'));

            return $user;
        });

        return TransactionStatCollection::make($users);
    }

    public function v1(Request $request)
    {
        $user = auth()->user();

        $dateRange = DateRangeValidator::parse($request, Carbon::now()->startOfDay(), Carbon::now()->endOfDay())
            ->validateDays(31);
        $startedAt = $dateRange->startedAt;
        $endedAt = $dateRange->endedAt;

        $daiso = Transaction::where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->where('confirmed_at', '>=', $startedAt)
            ->where('confirmed_at', '<', $endedAt)
            ->whereHas('to', function ($users) use ($user) {
                $users->whereDescendantOrSelf($user);
            })
            ->leftJoin('transaction_fees', function($join) {
                $join->on('transaction_fees.transaction_id', '=', 'transactions.id');
                $join->on('transaction_fees.user_id', '=', 'transactions.to_id');
            })
            ->groupBy('to_id')
            ->select([
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(amount) AS total_amount'),
                DB::raw('SUM(actual_fee) AS total_fee'),
                'to_id'
            ])
            ->get()
            ->keyBy('to_id');

        $xiafa = Transaction::whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->where('sub_type', Transaction::SUB_TYPE_WITHDRAW)
            ->where('confirmed_at', '>=', $startedAt)
            ->where('confirmed_at', '<', $endedAt)
            ->whereHas('from', function ($users) use ($user) {
                $users->whereDescendantOrSelf($user);
            })
            ->leftJoin('transaction_fees', function($join) {
                $join->on('transaction_fees.transaction_id', '=', 'transactions.id');
                $join->on('transaction_fees.user_id', '=', 'transactions.from_id');
            })
            ->groupBy('from_id')
            ->select([
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(amount) AS total_amount'),
                DB::raw('SUM(actual_fee) AS total_fee'),
                'from_id'
            ])
            ->get()
            ->keyBy('from_id');

        $daifu = Transaction::whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->where('sub_type', Transaction::SUB_TYPE_AGENCY_WITHDRAW)
            ->where('confirmed_at', '>=', $startedAt)
            ->where('confirmed_at', '<', $endedAt)
            ->whereHas('from', function ($users) use ($user) {
                $users->whereDescendantOrSelf($user);
            })
            ->leftJoin('transaction_fees', function($join) {
                $join->on('transaction_fees.transaction_id', '=', 'transactions.id');
                $join->on('transaction_fees.user_id', '=', 'transactions.from_id');
            })
            ->groupBy('from_id')
            ->select([
                DB::raw('COUNT(*) AS count'),
                DB::raw('SUM(amount) AS total_amount'),
                DB::raw('SUM(actual_fee) AS total_fee'),
                'from_id'
            ])
            ->get()
            ->keyBy('from_id');

        $descendants = $user->descendants()->get();
        $result = $descendants->prepend($user)->map(function ($user) use ($daiso, $xiafa, $daifu) {
            return [
                'id' => $user->id,
                'parent_id' => $user->parent_id,
                'name' => $user->name,
                'username' => $user->username,
                'stats' => [
                    'daiso' => $daiso[$user->id] ?? ['count' => 0, 'total_amount' => 0, 'total_fee' => 0],
                    'xiafa' => $xiafa[$user->id] ?? ['count' => 0, 'total_amount' => 0, 'total_fee' => 0],
                    'daifu' => $daifu[$user->id] ?? ['count' => 0, 'total_amount' => 0, 'total_fee' => 0],
                ]
            ];
        });

        return response()->json(['data' => $result]);
    }
}
