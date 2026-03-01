<?php

namespace App\Http\Controllers\Admin;

use App\Repository\StatisticsRepository;
use App\Utils\DateRangeValidator;
use Carbon\Carbon;
use App\Utils\SignatureCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function __construct(
        private readonly StatisticsRepository $statisticsRepository
    ) {}

    public function index(Request $request)
    {
        $admin = User::where([
            ['username', $request->username],
            ['role', User::ROLE_ADMIN]
        ])->first();

        abort_unless(isset($admin), Response::HTTP_BAD_REQUEST);

        if (!SignatureCalculator::verify($request->except('sign'), $admin->secret_key, $request->sign)) {
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

        $transactions = $this->statisticsRepository->getTransactionStatsByUsername($start, $end);
        $withdraws = $this->statisticsRepository->getWithdrawStatsByUsername($start, $end);

        $todayTransactions = $this->statisticsRepository->getTransactionStatsByUsername($todayStart, $todayEnd);
        $todayWithdraws = $this->statisticsRepository->getWithdrawStatsByUsername($todayStart, $todayEnd);

        $systemProfit = $this->statisticsRepository->getSystemProfit($start, $end);

        return response()->json(compact('transactions', 'todayTransactions', 'withdraws', 'todayWithdraws', 'systemProfit'));
    }

    public function date(Request $request)
    {
        $admin = User::where([
            ['username', $request->username],
            ['role', User::ROLE_ADMIN]
        ])->first();

        abort_unless(isset($admin), Response::HTTP_BAD_REQUEST);

        if (!SignatureCalculator::verify($request->except('sign'), $admin->secret_key, $request->sign)) {
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

        $transactions = $this->statisticsRepository->getTransactionStatsByDate($start, $end);
        $withdraws = $this->statisticsRepository->getWithdrawStatsByDate($start, $end);

        $todayTransactions = $this->statisticsRepository->getTransactionStatsByDate($todayStart, $todayEnd);
        $todayWithdraws = $this->statisticsRepository->getWithdrawStatsByDate($todayStart, $todayEnd);

        $systemProfit = $this->statisticsRepository->getSystemProfit($start, $end);

        return response()->json(compact('transactions', 'todayTransactions', 'withdraws', 'todayWithdraws', 'systemProfit'));
    }

    public function v1(Request $request)
    {
        $dateRange = DateRangeValidator::parse($request, now()->startOfDay(), now()->endOfDay())
            ->validateDays(31);
        $startedAt = $dateRange->startedAt;
        $endedAt = $dateRange->endedAt;
        $timeType = $request->timeType == "created_at" ? "created_at" : "confirmed_at";

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
