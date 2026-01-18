<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Services\Withdraw\AgencyWithdrawService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AgencyWithdrawController extends Controller
{
    public function store(Request $request, AgencyWithdrawService $service)
    {
        $this->validate($request, [
            'bank_card_number' => 'required|max:50',
            'bank_card_holder_name' => 'max:50',
            'bank_name' => 'required|max:50',
            'amount' => 'required|numeric|min:1',
        ]);

        $context = $service->buildContextFromMerchant($request, auth()->user());
        $service->execute($context);

        return response()->noContent(Response::HTTP_CREATED);
    }
}
