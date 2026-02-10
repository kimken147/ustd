<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\WalletHistoryCollection;
use App\Models\User;
use App\Models\WalletHistory;
use App\Utils\AmountDisplayTransformer;
use App\Utils\BCMathUtil;
use App\Utils\DateRangeValidator;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class WalletHistoryController extends Controller
{

    public function index(Request $request, BCMathUtil $bcMath)
    {
        $this->validate($request, [
            'started_at'       => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at'         => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'role'             => ['required', Rule::in(User::ROLE_PROVIDER, User::ROLE_MERCHANT)],
            'name_or_username' => 'nullable|string',
        ]);

        $dateRange = DateRangeValidator::parse($request, today())
            ->validateDays(31);
        $startedAt = $dateRange->startedAt;
        $endedAt = $dateRange->endedAt;

        $walletHistories = WalletHistory::whereHas('user', function (Builder $users) use ($request) {
            $users->where('role', $request->input('role'));

            $users->when(!is_null($request->name_or_username), function ($builder) use ($request) {
                $builder->where(function ($builder) use ($request) {
                    $builder->where('name', 'like', "%{$request->name_or_username}%")
                        ->orWhere('username', $request->name_or_username);
                });
            });
        })
            ->whereBetween('created_at', [$startedAt, $endedAt])
            ->where('type', WalletHistory::TYPE_SYSTEM_ADJUSTING);

        $increasingTotal = (clone $walletHistories)
            ->where(function ($builder) {
                $builder->where('delta->balance', '>=', 0);
            })
            ->first(
                [
                    DB::raw(
                        'SUM(delta->>"$.balance") AS total'
                    )
                ]
            );

        $decreasingTotal = (clone $walletHistories)
            ->where(function ($builder) {
                $builder->where('delta->balance', '<', 0);
            })
            ->first(
                [
                    DB::raw(
                        'SUM(delta->>"$.balance") AS total'
                    )
                ]
            );

        return WalletHistoryCollection::make(
            $walletHistories->with('user')->with('operator')->latest('created_at')->latest('id')->paginate(20)->appends($request->query->all())
        )
            ->additional([
                'meta' => [
                    'total_increased_balance_delta' => AmountDisplayTransformer::transform(
                        data_get($increasingTotal, 'total', '0.00')
                    ),
                    'total_decreased_balance_delta' => AmountDisplayTransformer::transform(
                        data_get($decreasingTotal, 'total', '0.00')
                    ),
                ]
            ]);
    }
}
