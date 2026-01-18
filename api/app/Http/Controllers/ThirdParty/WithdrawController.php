<?php

namespace App\Http\Controllers\ThirdParty;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdParty\Withdraw;
use App\Services\Withdraw\Exceptions\WithdrawValidationException;
use App\Services\Withdraw\WithdrawService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WithdrawController extends Controller
{
    public function store(Request $request, WithdrawService $service)
    {
        try {
            $context = $service->buildContextFromThirdParty($request);
            $result = $service->execute($context);

            return Withdraw::make($result->getTransaction())
                ->additional([
                    'http_status_code' => 201,
                    'message' => __('common.Submit successful'),
                ])
                ->response()
                ->setStatusCode(Response::HTTP_OK);
        } catch (WithdrawValidationException $e) {
            return $e->toThirdPartyResponse();
        }
    }
}
