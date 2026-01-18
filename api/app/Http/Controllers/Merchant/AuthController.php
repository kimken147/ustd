<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\Merchant\SubAccount;
use App\Http\Resources\Merchant\User as UserResource;
use App\Models\FeatureToggle;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Services\Auth\MerchantAuthService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    public function __construct(
        protected MerchantAuthService $authService,
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

    public function me(FeatureToggleRepository $featureToggleRepository)
    {
        abort_if(!in_array(auth()->user()->role, [User::ROLE_MERCHANT, User::ROLE_MERCHANT_SUB_ACCOUNT]), Response::HTTP_UNAUTHORIZED);

        $agencyWithdrawEnabled = $featureToggleRepository->enabled(FeatureToggle::ENABLE_AGENCY_WITHDRAW);

        $user = auth()->user()->realUser()->load('wallet', 'parent', 'userChannels', 'userChannels.channelGroup', 'userChannels.channelGroup.channel');

        if ($user->role == User::ROLE_MERCHANT) {
            $user->agency_withdraw_enabled = $agencyWithdrawEnabled && auth()->user()->agency_withdraw_enable;

            return UserResource::make($user)->additional([
                'meta' => [
                     'agency_withdraw_enabled' => $user->agency_withdraw_enable
                 ],
             ]);
        } else {
            return SubAccount::make($user);
        }
    }
}
