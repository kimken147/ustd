<?php

namespace App\Http\Controllers\Exchange;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\Exchange\User as UserResource;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Services\Auth\ExchangeAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        protected ExchangeAuthService $authService,
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

        return UserResource::make(auth()->user()->load('wallet'));
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
