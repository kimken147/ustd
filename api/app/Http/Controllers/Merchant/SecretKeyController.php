<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class SecretKeyController extends Controller
{

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  Authenticator  $authenticator
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function __invoke(Request $request, Authenticator $authenticator)
    {
        if (!auth()->user()->google2fa_enable) {
            return $this->secretKeyResponse();
        }

        $this->validate($request, [
            config('google2fa.otp_input') => 'required|string',
        ]);

        /** @var Authenticator $authenticator */
        $authenticator = app(Authenticator::class)->bootStateless($request);

        abort_if(
            !$authenticator->isAuthenticated(),
            Response::HTTP_BAD_REQUEST,
            __('google2fa.Invalid OTP')
        );

        return $this->secretKeyResponse();
    }

    private function secretKeyResponse()
    {
        return response()->json([
            'data' => [
                'secret_key' => auth()->user()->secret_key,
            ],
        ]);
    }
}
