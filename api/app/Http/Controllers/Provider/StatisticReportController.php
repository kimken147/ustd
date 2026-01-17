<?php

namespace App\Http\Controllers\Provider;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Utils\AmountDisplayTransformer;
use App\Http\Controllers\Controller;

use App\Http\Resources\Provider\MemberStatisticsCollection;

use App\Repository\FeatureToggleRepository;
use App\Models\User;
use App\Models\WalletHistory;

class StatisticReportController extends Controller
{
    public function __invoke(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        $this->validate($request, [
            'type' => 'required',
            'started_at' => ['required', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at' => ['date_format:'.DateTimeInterface::ATOM],
        ]);

        $type = $request->type;
        $startedAt = optional(Carbon::make($request->started_at))->tz(config('app.timezone'));
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();
        $userId = auth()->user()->getKey();

        if ($type == 'member') {
            $query = User::with([
                    'successPaufenTransactions' => function ($query) use ($startedAt, $endedAt) {
                        return $query->whereBetween('created_at', [$startedAt, $endedAt]);
                    },
                    'successWithdraws' => function ($query) use ($startedAt, $endedAt) {
                        return $query->whereBetween('created_at', [$startedAt, $endedAt]);
                    },
                    'successDeposits' => function ($query) use ($startedAt, $endedAt) {
                        return $query->whereBetween('created_at', [$startedAt, $endedAt]);
                    },
                    'walletHistories' => function ($query) use ($startedAt, $endedAt) {
                        return $query->whereBetween('created_at', [$startedAt, $endedAt])
                        ->whereIn('type', [WalletHistory::TYPE_DEPOSIT_PROFIT, WalletHistory::TYPE_MATCHING_DEPOSIT_REWARD, WalletHistory::TYPE_TRANSACTION_REWARD, WalletHistory::TYPE_SYSTEM_ADJUSTING_PROFIT])
                        ->where('delta->profit', '>', 0);
                    }
                ])
                ->where('role', User::ROLE_PROVIDER)
                ->where('parent_id', $userId)
                ->where(function ($builder) use ($request) {
                    $builder->where('name', 'like', "%{$request->q}%")
                        ->orWhere('username', 'like', "%{$request->q}%");
                });

            $users = $query->get();

            $totalDeposit = AmountDisplayTransformer::transform($users->sum(function ($user) { return $user->successDeposits->sum('amount') ?? '0.00'; }));
            $totalTransaction = AmountDisplayTransformer::transform($users->sum(function ($user) { return $user->successPaufenTransactions->sum('amount') ?? '0.00'; }));
            $totalProfit = AmountDisplayTransformer::transform($users->sum(function ($user) { return $user->walletHistories->sum('delta.profit') ?? '0.00'; }));
            $totalWithdraw = AmountDisplayTransformer::transform($users->sum(function ($user) { return $user->successWithdraws->sum('amount') ?? '0.00'; }));

            return MemberStatisticsCollection::make($users)
                ->additional([
                    'meta' => [
                        'total_deposit' => $totalDeposit,
                        'total_transaction' => $totalTransaction,
                        'total_profit' => $totalProfit,
                        'total_withdraw' => $totalWithdraw
                    ]
                ]);
        } else if ($type == 'self') {
            $user = auth()->user();
            $user->load([
                'successPaufenTransactions' => function ($query) use ($startedAt, $endedAt) {
                    return $query->whereBetween('created_at', [$startedAt, $endedAt]);
                },
                'successWithdraws' => function ($query) use ($startedAt, $endedAt) {
                    return $query->whereBetween('created_at', [$startedAt, $endedAt]);
                },
                'successDeposits' => function ($query) use ($startedAt, $endedAt) {
                    return $query->whereBetween('created_at', [$startedAt, $endedAt]);
                },
                'walletHistories' => function ($query) use ($startedAt, $endedAt) {
                    return $query->whereBetween('created_at', [$startedAt, $endedAt])
                    ->whereIn('type', [WalletHistory::TYPE_DEPOSIT_PROFIT, WalletHistory::TYPE_MATCHING_DEPOSIT_REWARD, WalletHistory::TYPE_TRANSACTION_REWARD, WalletHistory::TYPE_SYSTEM_ADJUSTING_PROFIT])
                    ->where('delta->profit', '>', 0);
                }
            ]);
            $users = collect([$user]);

            $totalDeposit = AmountDisplayTransformer::transform($users->sum(function ($user) use ($startedAt, $endedAt) {
                return $user->successDeposits->sum('amount') ?? '0.00';
            }));
            $totalTransaction = AmountDisplayTransformer::transform($users->sum(function ($user) use ($startedAt, $endedAt) {
                return $user->successPaufenTransactions->sum('amount') ?? '0.00';
            }));
            $totalProfit = AmountDisplayTransformer::transform($users->sum(function ($user) use ($startedAt, $endedAt) {
                return $user->walletHistories->sum('delta.profit') ?? '0.00';
            }));
            $totalWithdraw = AmountDisplayTransformer::transform($users->sum(function ($user) use ($startedAt, $endedAt) {
                return $user->successWithdraws->sum('amount') ?? '0.00';
            }));

            return MemberStatisticsCollection::make($users)
                ->additional([
                    'meta' => [
                        'total_deposit' => $totalDeposit,
                        'total_transaction' => $totalTransaction,
                        'total_profit' => $totalProfit,
                        'total_withdraw' => $totalWithdraw
                    ]
                ]);
        }

        return response()->noContent();
    }
}