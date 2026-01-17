<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\User as UserResource;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\AmountDisplayTransformer;
use App\Utils\LoginThrottle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use Throwable;
use Stevebauman\Location\Facades\Location;

class AuthController extends Controller
{

    public function changePassword(Request $request)
    {
        $this->validate($request, [
            'old_password' => 'required',
            'new_password' => 'required',
        ]);

        abort_if(
            !Hash::check($request->old_password, auth()->user()->password),
            Response::HTTP_BAD_REQUEST,
            '旧密码错误'
        );

        if (auth()->user()->google2fa_enable) {
            $this->validate($request, [
                config('google2fa.otp_input') => 'required|string',
            ]);

            /** @var Authenticator $authenticator */
            $authenticator = app(Authenticator::class)->bootStateless($request);

            abort_if(!$authenticator->isAuthenticated(), Response::HTTP_BAD_REQUEST, __('google2fa.Invalid OTP'));
        }

        auth()->user()->update([
            'password' => Hash::make($request->new_password),
        ]);

        return response()->noContent(Response::HTTP_OK);
    }

    public function login(LoginRequest $request, LoginThrottle $loginThrottle)
    {
        abort_if($loginThrottle->blocked($request), Response::HTTP_BAD_REQUEST, '请稍后再试');

        auth()->setDefaultDriver('api');

        $credentials = $request->only('username', 'password') + ['role' => User::ROLE_PROVIDER];

        $user = User::where('username', $request->input('username'))->first();

        abort_if(!$user, Response::HTTP_BAD_REQUEST, __('auth.failed'));
        abort_if($user->status === User::STATUS_DISABLE, Response::HTTP_BAD_REQUEST, __('auth.account disabled'));

        if (!$token = auth('api')->attempt($credentials)) {
            abort_if($loginThrottle->count($request, $credentials['username']), Response::HTTP_BAD_REQUEST, '请稍后再试');

            $errorMessage = __('auth.failed');

            if ($loginThrottle->featureEnabled()) {
                $errorMessage = '帐号或密码错误，登入失败次数过多将会被系统封锁，请再次确认帐号密码！';
            }

            abort(Response::HTTP_BAD_REQUEST, $errorMessage);
        }

        if (auth('api')->user()->google2fa_enable) {
            $this->validate($request, [
                config('google2fa.otp_input') => 'required|string',
            ]);

            auth()->setDefaultDriver('api');

            /** @var Authenticator $authenticator */
            $authenticator = app(Authenticator::class)->bootStateless($request);

            if (!$authenticator->isAuthenticated()) {
                abort_if(
                    $loginThrottle->count($request, $credentials['username']),
                    Response::HTTP_BAD_REQUEST,
                    '请稍后再试'
                );

                $errorMessage = __('google2fa.Invalid OTP');

                if ($loginThrottle->featureEnabled()) {
                    $errorMessage = '谷歌验证码错误，失败次数过多将会被系统封锁，请务必再次确认！';
                }

                abort(Response::HTTP_BAD_REQUEST, $errorMessage);
            }
        }

        DB::transaction(function () {
            $ip = Arr::last(request()->ips());

            $city = str_replace('\'', ' ', optional(Location::get($ip))->cityName);

            auth('api')->user()->update([
                'last_login_at'   => now(),
                'last_login_ipv4' => $ip,
                'last_login_city' => $city
            ]);
        });

        $loginThrottle->clearCount($request);

        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type'   => 'bearer',
                'expires_in'   => auth('api')->factory()->getTTL() * 60,
            ],
        ]);
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

    public function preLogin(LoginRequest $request, LoginThrottle $loginThrottle)
    {
        abort_if($loginThrottle->blocked($request), Response::HTTP_BAD_REQUEST, '请稍后再试');

        auth()->setDefaultDriver('api');

        $credentials = $request->only('username', 'password') + ['role' => User::ROLE_PROVIDER];

        $user = User::where('username', $request->input('username'))->first();

        abort_if(!$user, Response::HTTP_BAD_REQUEST, __('auth.failed'));
        abort_if($user->status === User::STATUS_DISABLE, Response::HTTP_BAD_REQUEST, __('auth.account disabled'));

        if (auth('api')->attempt($credentials)) {
            abort_if(
                auth()->user()->status === User::STATUS_DISABLE,
                Response::HTTP_BAD_REQUEST,
                __('auth.account disabled')
            );

            return response()->json([
                'data' => [
                    'google2fa_enable' => auth('api')->user()->google2fa_enable,
                ],
            ]);
        }

        abort_if($loginThrottle->count($request, $credentials['username']), Response::HTTP_BAD_REQUEST, '请稍后再试');

        $errorMessage = __('auth.failed');

        if ($loginThrottle->featureEnabled()) {
            $errorMessage = '帐号或密码错误，登入失败次数过多将会被系统封锁，请再次确认帐号密码！';
        }

        abort(Response::HTTP_BAD_REQUEST, $errorMessage);
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
