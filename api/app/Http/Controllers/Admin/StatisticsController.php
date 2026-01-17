<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Model\User;
use App\Model\Transaction;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $admin = User::where([
            ['username', $request->username],
            ['role', User::ROLE_ADMIN]
        ])->first();

        abort_unless(isset($admin), Response::HTTP_BAD_REQUEST);

        $params = $request->except('sign');
        ksort($params);

        $sign = md5(urldecode(http_build_query($params) . '&secret_key=' . $admin->secret_key));

        if (!in_array(strtolower($request->sign), [$sign])) {
            return abort(500);
        }

        if ($request->has('month')) {
            $start = Carbon::createFromDate($request->year, $request->month)->firstOfMonth();
            $end = Carbon::createFromDate($request->year, $request->month)->endOfMonth();
        } else {
            $start = Carbon::createFromDate($request->year, 1)->firstOfMonth();
            $end = Carbon::createFromDate($request->year, 1)->endOfYear();
        }
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $status1 = Transaction::STATUS_SUCCESS;
        $status2 = Transaction::STATUS_MANUAL_SUCCESS;
        $withdrawStatus1 = Transaction::TYPE_PAUFEN_WITHDRAW;
        $withdrawStatus2 = Transaction::TYPE_NORMAL_WITHDRAW;

        $transactionSql = "
            SELECT u.username AS username, COUNT(*) AS count, SUM(amount) AS sum
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_to_id_index)
            LEFT JOIN users AS u ON t.to_id = u.id
            WHERE t.status IN ({$status1}, {$status2})
            AND u.role = 3
            AND t.confirmed_at BETWEEN '{$start}' AND '{$end}'
            GROUP BY to_id
            ORDER BY username
        ";

        $todayTransactionSql = "
            SELECT u.username AS username, COUNT(*) AS count, SUM(amount) AS sum
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_to_id_index)
            LEFT JOIN users AS u ON t.to_id = u.id
            WHERE t.status IN ({$status1}, {$status2})
            AND u.role = 3
            AND t.confirmed_at BETWEEN '{$todayStart}' AND '{$todayEnd}'
            GROUP BY to_id
            ORDER BY username
        ";

        $withdrawSql = "
            SELECT u.username AS username, COUNT(*) AS count, SUM(amount) AS sum
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_from_id_index)
            LEFT JOIN users AS u ON t.from_id = u.id
            WHERE t.status IN ({$status1}, {$status2})
            AND t.sub_type IN ({$withdrawStatus1}, {$withdrawStatus2})
            AND u.role = 3
            AND t.confirmed_at BETWEEN '{$start}' AND '{$end}'
            GROUP BY from_id
            ORDER BY username
        ";

        $todayWithdrawSql = "
            SELECT u.username AS username, COUNT(*) AS count, SUM(amount) AS sum
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_from_id_index)
            LEFT JOIN users AS u ON t.from_id = u.id
            WHERE t.status IN ({$status1}, {$status2})
            AND t.sub_type IN ({$withdrawStatus1}, {$withdrawStatus2})
            AND u.role = 3
            AND t.confirmed_at BETWEEN '{$todayStart}' AND '{$todayEnd}'
            GROUP BY from_id
            ORDER BY username
        ";

        $systemProfitSql = "
            SELECT SUM(f.actual_profit) AS sum FROM transaction_fees AS f
            LEFT JOIN transactions AS t ON f.transaction_id = t.id
            WHERE t.confirmed_at BETWEEN '{$start}' AND '{$end}'
            AND f.user_id = 0
        ";

        $transactions = collect(DB::select(DB::raw($transactionSql)))->keyBy('username');
        $withdraws = collect(DB::select(DB::raw($withdrawSql)))->keyBy('username');

        $todayTransactions = collect(DB::select(DB::raw($todayTransactionSql)))->keyBy('username');
        $todayWithdraws = collect(DB::select(DB::raw($todayWithdrawSql)))->keyBy('username');

        $systemProfit = DB::select(DB::raw($systemProfitSql))[0];

        return response()->json(compact('transactions', 'todayTransactions', 'withdraws', 'todayWithdraws', 'systemProfit'));
    }

    public function date(Request $request)
    {
        $admin = User::where([
            ['username', $request->username],
            ['role', User::ROLE_ADMIN]
        ])->first();

        abort_unless(isset($admin), Response::HTTP_BAD_REQUEST);

        $params = $request->except('sign');
        ksort($params);

        $sign = md5(urldecode(http_build_query($params) . '&secret_key=' . $admin->secret_key));

        if (!in_array(strtolower($request->sign), [$sign])) {
            return abort(500);
        }

        if ($request->has('month')) {
            $start = Carbon::createFromDate($request->year, $request->month)->firstOfMonth();
            $end = Carbon::createFromDate($request->year, $request->month)->endOfMonth();
        } else {
            $start = Carbon::createFromDate($request->year, 1)->firstOfMonth();
            $end = Carbon::createFromDate($request->year, 1)->endOfYear();
        }
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        $status1 = Transaction::STATUS_SUCCESS;
        $status2 = Transaction::STATUS_MANUAL_SUCCESS;
        $withdrawStatus1 = Transaction::TYPE_PAUFEN_WITHDRAW;
        $withdrawStatus2 = Transaction::TYPE_NORMAL_WITHDRAW;

        $transactionSql = "
            SELECT COUNT(*) AS count, SUM(amount) AS sum, DATE(confirmed_at) AS date
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_to_id_index)
            LEFT JOIN users AS u ON t.to_id = u.id
            WHERE t.status IN ({$status1}, {$status2})
            AND u.role = 3
            AND t.confirmed_at BETWEEN '{$start}' AND '{$end}'
            GROUP BY date
        ";

        $todayTransactionSql = "
            SELECT COUNT(*) AS count, SUM(amount) AS sum, DATE(confirmed_at) AS date
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_to_id_index)
            LEFT JOIN users AS u ON t.to_id = u.id
            WHERE t.status IN ({$status1}, {$status2})
            AND u.role = 3
            AND t.confirmed_at BETWEEN '{$todayStart}' AND '{$todayEnd}'
            GROUP BY date
        ";

        $withdrawSql = "
            SELECT COUNT(*) AS count, SUM(amount) AS sum, DATE(confirmed_at) AS date
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_from_id_index)
            LEFT JOIN users AS u ON t.from_id = u.id
            WHERE t.status IN ({$status1}, {$status2})
            AND t.sub_type IN ({$withdrawStatus1}, {$withdrawStatus2})
            AND u.role = 3
            AND t.confirmed_at BETWEEN '{$start}' AND '{$end}'
            GROUP BY date
        ";

        $todayWithdrawSql = "
            SELECT COUNT(*) AS count, SUM(amount) AS sum, DATE(confirmed_at) AS date
            FROM transactions AS t FORCE INDEX(transactions_confirmed_at_from_id_index)
            LEFT JOIN users AS u ON t.from_id = u.id
            WHERE t.status IN ({$status1}, {$status2})
            AND t.sub_type IN ({$withdrawStatus1}, {$withdrawStatus2})
            AND u.role = 3
            AND t.confirmed_at BETWEEN '{$todayStart}' AND '{$todayEnd}'
            GROUP BY date
        ";

        $systemProfitSql = "
            SELECT SUM(f.actual_profit) AS sum FROM transaction_fees AS f
            LEFT JOIN transactions AS t ON f.transaction_id = t.id
            WHERE t.confirmed_at BETWEEN '{$start}' AND '{$end}'
            AND f.user_id = 0
        ";

        $transactions = collect(DB::select(DB::raw($transactionSql)))->keyBy('date');
        $withdraws = collect(DB::select(DB::raw($withdrawSql)))->keyBy('date');

        $todayTransactions = collect(DB::select(DB::raw($todayTransactionSql)))->keyBy('date');
        $todayWithdraws = collect(DB::select(DB::raw($todayWithdrawSql)))->keyBy('date');

        $systemProfit = DB::select(DB::raw($systemProfitSql))[0];

        return response()->json(compact('transactions', 'todayTransactions', 'withdraws', 'todayWithdraws', 'systemProfit'));
    }

    public function v1(Request $request)
    {
        $startedAt = $request->has('started_at') ? Carbon::parse($request->started_at) : Carbon::now()->startOfDay();
        $endedAt = $request->has('ended_at') ? Carbon::parse($request->ended_at) : Carbon::now()->endOfDay();
        $timeType = $request->timeType == "created_at" ? "created_at" : "confirmed_at";

        abort_if($startedAt->diffInDays($endedAt) > 31, Response::HTTP_BAD_REQUEST, '时间区间最多一次筛选一个月，请重新调整时间');

        // 确保 $startedAt 和 $endedAt 是 Carbon 实例
        $startedAt = Carbon::parse($startedAt);
        $endedAt = Carbon::parse($endedAt);

        // 获取交易类型常量
        $typePaufenTransaction = Transaction::TYPE_PAUFEN_TRANSACTION;
        $typePaufenWithdraw = Transaction::TYPE_PAUFEN_WITHDRAW;
        $typeNormalWithdraw = Transaction::TYPE_NORMAL_WITHDRAW;
        $subTypeWithdraw = Transaction::SUB_TYPE_WITHDRAW;
        $subTypeAgencyWithdraw = Transaction::SUB_TYPE_AGENCY_WITHDRAW;
        $statusSuccess = Transaction::STATUS_SUCCESS;
        $statusManualSuccess = Transaction::STATUS_MANUAL_SUCCESS;

        // 统计查询
        $results = DB::table('transactions')
            ->leftJoin('transaction_fees as fees_user', 'fees_user.transaction_id', '=', 'transactions.id')
            ->select('fees_user.user_id')
            ->selectRaw('SUM(CASE WHEN transactions.type = ? AND transactions.to_id = fees_user.user_id THEN 1 ELSE 0 END) AS daiso_count', [$typePaufenTransaction])
            ->selectRaw('SUM(CASE WHEN transactions.type = ? AND transactions.to_id = fees_user.user_id THEN transactions.amount ELSE 0 END) AS daiso_total_amount', [$typePaufenTransaction])
            ->selectRaw('SUM(CASE WHEN transactions.type = ? THEN fees_user.actual_fee ELSE 0 END) AS daiso_total_fee', [$typePaufenTransaction])
            ->selectRaw('SUM(CASE WHEN transactions.type = ? THEN fees_user.actual_profit ELSE 0 END) AS daiso_total_profit', [$typePaufenTransaction])
            ->selectRaw('SUM(CASE WHEN transactions.type = ? AND (transactions.from_id = fees_user.user_id OR transactions.to_id = fees_user.user_id) THEN (SELECT SUM(actual_profit) FROM transaction_fees WHERE transaction_fees.transaction_id = transactions.id AND transaction_fees.user_id = 0) ELSE 0 END) AS daiso_system_profit', [$typePaufenTransaction])

            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? AND transactions.from_id = fees_user.user_id THEN 1 ELSE 0 END) AS xiafa_count', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeWithdraw])
            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? AND transactions.from_id = fees_user.user_id THEN transactions.amount ELSE 0 END) AS xiafa_total_amount', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeWithdraw])
            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? THEN fees_user.actual_fee ELSE 0 END) AS xiafa_total_fee', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeWithdraw])
            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? THEN fees_user.actual_profit ELSE 0 END) AS xiafa_total_profit', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeWithdraw])
            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? AND (transactions.from_id = fees_user.user_id OR transactions.to_id = fees_user.user_id) THEN (SELECT SUM(actual_profit) FROM transaction_fees WHERE transaction_fees.transaction_id = transactions.id AND transaction_fees.user_id = 0) ELSE 0 END) AS xiafa_system_profit', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeWithdraw])

            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? AND transactions.from_id = fees_user.user_id THEN 1 ELSE 0 END) AS daifu_count', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeAgencyWithdraw])
            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? AND transactions.from_id = fees_user.user_id THEN transactions.amount ELSE 0 END) AS daifu_total_amount', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeAgencyWithdraw])
            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? THEN fees_user.actual_fee ELSE 0 END) AS daifu_total_fee', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeAgencyWithdraw])
            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? THEN fees_user.actual_profit ELSE 0 END) AS daifu_total_profit', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeAgencyWithdraw])
            ->selectRaw('SUM(CASE WHEN transactions.type IN (?, ?) AND transactions.sub_type = ? AND (transactions.from_id = fees_user.user_id OR transactions.to_id = fees_user.user_id) THEN (SELECT SUM(actual_profit) FROM transaction_fees WHERE transaction_fees.transaction_id = transactions.id AND transaction_fees.user_id = 0) ELSE 0 END) AS daifu_system_profit', [$typePaufenWithdraw, $typeNormalWithdraw, $subTypeAgencyWithdraw])

            ->whereBetween('transactions.' . $timeType, [$startedAt, $endedAt])
            ->whereIn('transactions.status', [$statusSuccess, $statusManualSuccess])
            ->groupBy('fees_user.user_id')
            ->get()
            ->keyBy('user_id');

        // 获取用户数据
        $users = User::where('role', User::ROLE_MERCHANT)
            ->when($request->merchant_name_or_username, function ($builder, $merchantNameOrUsername) {
                $builder->whereIn('username', $merchantNameOrUsername);
            })
            ->get();

        // 生成最终结果
        $result = $users->map(function ($user) use ($results) {
            $userStats = $results->get($user->id);

            return [
                'id' => $user->id,
                'parent_id' => $user->parent_id,
                'name' => $user->name,
                'username' => $user->username,
                'stats' => [
                    'daiso' => [
                        'count' => $userStats->daiso_count ?? 0,
                        'total_amount' => $userStats->daiso_total_amount ?? '0.00',
                        'total_fee' => $userStats->daiso_total_fee ?? '0.00',
                        'total_profit' => $userStats->daiso_total_profit ?? '0.00',
                        'system_profit' => $userStats->daiso_system_profit ?? '0.00',
                    ],
                    'xiafa' => [
                        'count' => $userStats->xiafa_count ?? 0,
                        'total_amount' => $userStats->xiafa_total_amount ?? '0.00',
                        'total_fee' => $userStats->xiafa_total_fee ?? '0.00',
                        'total_profit' => $userStats->xiafa_total_profit ?? '0.00',
                        'system_profit' => $userStats->xiafa_system_profit ?? '0.00',
                    ],
                    'daifu' => [
                        'count' => $userStats->daifu_count ?? 0,
                        'total_amount' => $userStats->daifu_total_amount ?? '0.00',
                        'total_fee' => $userStats->daifu_total_fee ?? '0.00',
                        'total_profit' => $userStats->daifu_total_profit ?? '0.00',
                        'system_profit' => $userStats->daifu_system_profit ?? '0.00',
                    ],
                ],
            ];
        });

        return response()->json(['data' => $result]);
    }
}
