<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListWalletHistoryRequest;
use App\Http\Resources\WalletHistoryCollection;
use App\Utils\AmountDisplayTransformer;
use App\Model\FeatureToggle;
use App\Repository\FeatureToggleRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WalletHistoryController extends Controller
{

    public function index(
        ListWalletHistoryRequest $request,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $startedAt = $request->started_at ? Carbon::make($request->started_at)->tz(config('app.timezone')) : today();
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        abort_if(
            $featureToggleRepository->enabled(FeatureToggle::VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS) &&
            now()->diffInDays($startedAt) > $featureToggleRepository->valueOf(FeatureToggle::VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS, 30),
            Response::HTTP_BAD_REQUEST,
            '查无资料'
        );

        abort_if(
            !$startedAt
            || $startedAt->diffInDays($endedAt) > 31,
            Response::HTTP_BAD_REQUEST,
            '时间区间最多一次筛选一个月，请重新调整时间'
        );

        $walletHistories = auth()->user()->walletHistories()
            ->where('created_at', '>=', $startedAt)
            ->when($request->ended_at, function ($builder, $endedAt) {
                $endedAt = Carbon::make($endedAt)->tz(config('app.timezone'));

                $builder->where('created_at', '<=', $endedAt);
            })
            ->when($request->type, function ($builder, $type) {
                $builder->whereIn('type', $type);
            })
            ->when($request->note, function ($builder, $note) {
                $builder->where('note', 'like', "%{$note}%");
            });

        $walletBalanceTotal = (clone $walletHistories)
            ->first(
                [
                    DB::raw(
                        'SUM(delta->>"$.balance") + SUM(delta->>"$.profit") + SUM(delta->>"$.frozen_balance") AS total'
                    )
                ]
            );

        return WalletHistoryCollection::make(
            $walletHistories->latest('created_at')->latest('id')->paginate(20)->appends($request->query->all())
        )
            ->additional([
                'meta' => [
                    'wallet_balance_total' =>  AmountDisplayTransformer::transform(data_get($walletBalanceTotal, 'total', '0.00'))
                ]
            ]);
    }
}
