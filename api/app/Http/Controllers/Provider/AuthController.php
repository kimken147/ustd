<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\User as UserResource;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Services\Auth\ProviderAuthService;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuthController extends Controller
{
    public function __construct(
        protected ProviderAuthService $authService,
    ) {}

    public function login(LoginRequest $request)
    {
        return response()->json([
            'data' => $this->authService->login($request),
        ]);
    }

    public function preLogin(LoginRequest $request)
    {
        return response()->json([
            'data' => $this->authService->preLogin($request),
        ]);
    }

    public function changePassword(Request $request)
    {
        $this->authService->changePassword($request);

        return response()->noContent(Response::HTTP_OK);
    }

    public function me(Request $request)
    {
        abort_if(auth()->user()->role !== User::ROLE_PROVIDER, Response::HTTP_UNAUTHORIZED);

        $response = UserResource::make(auth()->user()->load('wallet', 'parent', 'userChannels', 'controlDownlines'));
        $today = today();
        $yesterday = today()->subDay();
        $statsCacheKey = 'profile_stats_of_' . auth()->user()->getKey();

        if ($request->boolean('with_stats')) {
            $stats = Cache::get($statsCacheKey);

            if (empty($stats)) {
                $todayTransactionTotalProfit = TransactionFee::whereIn(
                    'transaction_id',
                    Transaction::where([
                        'type'    => Transaction::TYPE_PAUFEN_TRANSACTION
                    ])
                        ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                        ->where('confirmed_at', '>=', $today)
                        ->select(['id'])
                )
                    ->where(function (Builder $transactionFees) {
                        if (auth()->user()->isRoot()) {
                            $transactionFees->where(function (Builder $transactionFees) {
                                $transactionFees->where('user_id', auth()->user()->getKey())
                                    ->whereIn('account_mode', [User::ACCOUNT_MODE_GENERAL, User::ACCOUNT_MODE_DEPOSIT]);
                            })
                                ->orWhere(function (Builder $transactionFees) {
                                    $transactionFees->whereIn('user_id', User::whereDescendantOf(auth()->user())->select('id'))
                                        ->where('account_mode', User::ACCOUNT_MODE_DEPOSIT);
                                });
                        } else {
                            $transactionFees->where('user_id', auth()->user()->getKey())
                                ->where('account_mode', User::ACCOUNT_MODE_GENERAL);
                        }
                    })
                    ->first([DB::raw('SUM(profit) AS total_profit')]);

                $yesterdayTransactionTotalProfit = TransactionFee::whereIn(
                    'transaction_id',
                    Transaction::where([
                        'type'    => Transaction::TYPE_PAUFEN_TRANSACTION
                    ])->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                        ->where('confirmed_at', '>=', $yesterday)
                        ->where('confirmed_at', '<', $today)
                        ->select(['id'])
                )
                    ->where(function (Builder $transactionFees) {
                        if (auth()->user()->isRoot()) {
                            $transactionFees->where(function (Builder $transactionFees) {
                                $transactionFees->where('user_id', auth()->user()->getKey())
                                    ->whereIn('account_mode', [User::ACCOUNT_MODE_GENERAL, User::ACCOUNT_MODE_DEPOSIT]);
                            })
                                ->orWhere(function (Builder $transactionFees) {
                                    $transactionFees->whereIn('user_id', User::whereDescendantOf(auth()->user())->select('id'))
                                        ->where('account_mode', User::ACCOUNT_MODE_DEPOSIT);
                                });
                        } else {
                            $transactionFees->where('user_id', auth()->user()->getKey())
                                ->where('account_mode', User::ACCOUNT_MODE_GENERAL);
                        }
                    })
                    ->first([DB::raw('SUM(profit) AS total_profit')]);

                $todayDescendantsTransactionTotalProfit = TransactionFee::whereIn(
                    'transaction_id',
                    Transaction::where([
                        'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
                    ])
                        ->whereIn('from_id', User::whereDescendantOf(auth()->user())->select('id'))
                        ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                        ->where('confirmed_at', '>=', $today)
                        ->select(['id'])
                )
                    ->where(function (Builder $transactionFees) {
                        if (auth()->user()->isRoot()) {
                            $transactionFees->where(function (Builder $transactionFees) {
                                $transactionFees->whereIn('user_id', User::whereDescendantOf(auth()->user())->select('id'))
                                    ->whereIn('account_mode', [User::ACCOUNT_MODE_GENERAL, User::ACCOUNT_MODE_DEPOSIT]);
                            });
                        } else {
                            $transactionFees->whereIn('user_id', User::whereDescendantOf(auth()->user())->select('id'))
                                ->where('account_mode', User::ACCOUNT_MODE_GENERAL);
                        }
                    })
                    ->first([DB::raw('SUM(profit) AS total_profit')]);

                $yesterdayDescendantsTransactionTotalProfit = TransactionFee::whereIn(
                    'transaction_id',
                    Transaction::where([
                        'type' => Transaction::TYPE_PAUFEN_TRANSACTION,
                    ])
                        ->whereIn('from_id', User::whereDescendantOf(auth()->user())->select('id'))
                        ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                        ->where('confirmed_at', '>=', $yesterday)
                        ->where('confirmed_at', '<', $today)
                        ->select(['id'])
                )
                    ->where(function (Builder $transactionFees) {
                        if (auth()->user()->isRoot()) {
                            $transactionFees->where(function (Builder $transactionFees) {
                                $transactionFees->whereIn('user_id', User::whereDescendantOf(auth()->user())->select('id'))
                                    ->whereIn('account_mode', [User::ACCOUNT_MODE_GENERAL, User::ACCOUNT_MODE_DEPOSIT]);
                            });
                        } else {
                            $transactionFees->whereIn('user_id', User::whereDescendantOf(auth()->user())->select('id'))
                                ->where('account_mode', User::ACCOUNT_MODE_GENERAL);
                        }
                    })
                    ->first([DB::raw('SUM(profit) AS total_profit')]);

                $stats = [
                    'today_self_transaction_total_profit'            => AmountDisplayTransformer::transform(data_get(
                        $todayTransactionTotalProfit,
                        'total_profit',
                        '0.00'
                    )),
                    'yesterday_self_transaction_total_profit'        => AmountDisplayTransformer::transform(data_get(
                        $yesterdayTransactionTotalProfit,
                        'total_profit',
                        '0.00'
                    )),
                    'today_descendants_transaction_total_profit'     => AmountDisplayTransformer::transform(data_get(
                        $todayDescendantsTransactionTotalProfit,
                        'total_profit',
                        '0.00'
                    )),
                    'yesterday_descendants_transaction_total_profit' => AmountDisplayTransformer::transform(data_get(
                        $yesterdayDescendantsTransactionTotalProfit,
                        'total_profit',
                        '0.00'
                    )),
                ];

                Cache::put($statsCacheKey, $stats, now()->addMinute());
            }

            $response->additional([
                'meta' => $stats,
            ]);
        }

        return $response;
    }

    public function updateMe(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        $this->validate($request, [
            'ready_for_matching' => 'boolean',
        ]);

        $user = auth()->user();
        if ($request->has('ready_for_matching')) {

            abort_if(
                $request->boolean('ready_for_matching') && !$user->transaction_enable,
                Response::HTTP_BAD_REQUEST,
                '交易功能未开启'
            );
            Log::debug("碼商搶單開關: " . $request->input($request->input("ready_for_matching")) . ", " . $user->name);

            try {
                DB::beginTransaction();
                $user->update([
                    'ready_for_matching' => $request->boolean('ready_for_matching'),
                    'last_activity_at' => now()
                ]);
                User::whereIn('id', $user->controlDownlines->pluck('id'))->update([
                    'ready_for_matching' => $request->boolean('ready_for_matching'),
                    'last_activity_at' => now()
                ]);
                DB::commit();
            } catch (Throwable $throw) {
                DB::rollback();
            }
        }

        return UserResource::make($user->load('wallet', 'parent', 'userChannels'));
    }
}
