<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\User as UserResource;
use App\Services\Auth\AdminAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AdminAuthService $authService,
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

    public function me()
    {
        return UserResource::make(auth()->user()->realUser()->load('permissions'));
    }
}
