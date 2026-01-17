<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListWalletHistoryRequest;
use App\Http\Requests\UpdateWalletHistoryRequest;
use App\Http\Resources\WalletHistoryCollection;
use App\Utils\AmountDisplayTransformer;
use App\Models\User;
use App\Models\WalletHistory;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class UserWalletHistoryController extends Controller
{

    public function index(User $user, ListWalletHistoryRequest $request)
    {
        $startedAt = $request->started_at ? Carbon::make($request->started_at)->tz(config('app.timezone')) : today();

        $walletHistories = $user->walletHistories()
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
                        'SUM(delta->>"$.balance") + SUM(delta->>"$.profit") + SUM(delta->>"$.frozen_balance")AS total'
                    )
                ]
            );

        return WalletHistoryCollection::make(
            $walletHistories->with('operator')->latest('created_at')->latest('id')->paginate(20)->appends($request->query->all())
        )
            ->additional([
                'meta' => [
                    'wallet_balance_total' =>  AmountDisplayTransformer::transform(data_get($walletBalanceTotal, 'total', '0.00'))
                ]
            ]);
    }

    public function update(User $user, WalletHistory $walletHistory, UpdateWalletHistoryRequest $request)
    {
        abort_if($user->getKey() !== $walletHistory->user_id, Response::HTTP_NOT_FOUND);

        $walletHistory->update([
            'note' => $request->note,
        ]);

        return \App\Http\Resources\WalletHistory::make($walletHistory);
    }
}
