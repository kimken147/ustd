<?php

namespace App\Http\Controllers\Exchange;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\Exchange\User as UserResource;
use App\Models\FeatureToggle;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FALaravel\Support\Authenticator;

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

        abort_if($user->status === User::STATUS_DISABLE, Response::HTTP_BAD_REQUEST, '登入失败');
        abort_if(!$user->exchange_mode_enable, Response::HTTP_BAD_REQUEST, '登入失败');

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
                abort_if($loginThrottle->count($request, $credentials['username']), Response::HTTP_BAD_REQUEST,
                    '请稍后再试');

                $errorMessage = __('google2fa.Invalid OTP');

                if ($loginThrottle->featureEnabled()) {
                    $errorMessage = '谷歌验证码错误，失败次数过多将会被系统封锁，请务必再次确认！';
                }

                abort(Response::HTTP_BAD_REQUEST, $errorMessage);
            }
        }

        DB::transaction(function () {
            auth('api')->user()->update([
                'last_login_at'   => now(),
                'last_login_ipv4' => Arr::last(request()->ips()),
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

        return UserResource::make(auth()->user()->load('wallet'));
    }

    public function preLogin(LoginRequest $request, LoginThrottle $loginThrottle)
    {
        abort_if($loginThrottle->blocked($request), Response::HTTP_BAD_REQUEST, '请稍后再试');

        auth()->setDefaultDriver('api');

        $credentials = $request->only('username', 'password') + ['role' => User::ROLE_PROVIDER];

        $user = User::where('username', $request->input('username'))->first();

        abort_if(!$user, Response::HTTP_BAD_REQUEST, __('auth.failed'));

        abort_if($user->status === User::STATUS_DISABLE, Response::HTTP_BAD_REQUEST, '登入失败');
        abort_if(!$user->exchange_mode_enable, Response::HTTP_BAD_REQUEST, '登入失败');

        if (auth('api')->attempt($credentials)) {
            abort_if(auth()->user()->status === User::STATUS_DISABLE, Response::HTTP_BAD_REQUEST,
                __('auth.account disabled'));

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

        if ($request->has('ready_for_matching')) {

            abort_if(
                $request->boolean('ready_for_matching') && !auth()->user()->transaction_enable,
                Response::HTTP_BAD_REQUEST,
                '交易功能未开启'
            );

            auth()->user()->update(['ready_for_matching' => $request->boolean('ready_for_matching')]);
        }

        return UserResource::make(auth()->user()->load('wallet'));
    }
}
