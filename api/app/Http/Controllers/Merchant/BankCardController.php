<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Resources\Merchant\BankCard as BankCardResource;
use App\Http\Resources\Merchant\BankCardCollection;
use App\Models\BankCard;
use App\Models\Channel;
use App\Models\Bank;
use App\Models\FeatureToggle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Repository\FeatureToggleRepository;
use App\Utils\ThirdPartyResponseUtil;

class BankCardController extends Controller
{

    public function index(Request $request)
    {
        $this->validate($request, [
            'status'      => 'nullable|array',
            'no_paginate' => 'nullable|boolean',
        ]);

        /** @var Builder $bankCards */
        $bankCards = BankCard::where('user_id', auth()->user()->getKey())
            ->latest('id');

        $bankCards->when($request->has('q'), function (Builder $bankCards) use ($request) {
            $bankCards->where(function (Builder $bankCards) use ($request) {
                $q = $request->q;

                $bankCards->where('bank_card_holder_name', 'like', "%$q%")
                    ->orWhere('bank_card_number', $q)
                    ->orWhere('bank_name', 'like', "%$q%");
            });
        });

        $bankCards->when($request->status, function (Builder $bankCards, $statusSet) {
            $bankCards->whereIn('status', $statusSet);
        });

        return BankCardCollection::make($request->no_paginate ? $bankCards->get() : $bankCards->paginate(20));
    }

    public function store(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        $this->validate($request, [
            'bank_card_holder_name' => 'nullable|string',
            'bank_card_number'      => 'nullable|string',
            'bank_name'             => 'nullable|string',
            'bank_province'         => 'nullable|string',
            'bank_city'             => 'nullable|string',
        ]);

        $bank = Bank::where('name', $request->input('bank_name'))->orWhere('code', $request->input('bank_name'))->first();

        $daifuBanks = Channel::where('type', Channel::TYPE_DEPOSIT_WITHDRAW)->get()->map(function ($channel) {
            return $channel->deposit_account_fields['merchant_can_withdraw_banks'] ?? [];
        })->flatten();

        if ($daifuBanks->isEmpty()) {
            $inDaifuBank = $bank;
        } else {
            $inDaifuBank = $daifuBanks->contains($request->input('bank_name'));
        }

        abort_if($featureToggleRepository->enabled(FeatureToggle::WITHDRAW_BANK_NAME_MAPPING) && !$inDaifuBank, Response::HTTP_BAD_GATEWAY, "不支援此银行");
        // if ($featureToggleRepository->enabled(FeatureToggle::WITHDRAW_BANK_NAME_MAPPING) && !$inDaifuBank) {
        //     return response()->json([
        //         'http_status_code' => Response::HTTP_BAD_REQUEST,
        //         'error_code'       => ThirdPartyResponseUtil::ERROR_CODE_BANK_NOT_FOUND,
        //         'message'          => '不支援此银行'
        //     ]);
        // }

        $bankCard = BankCard::create([
            'user_id'               => auth()->user()->getKey(),
            'status'                => BankCard::STATUS_REVIEW_PASSED,
            'bank_card_holder_name' => $request->bank_card_holder_name,
            'bank_card_number'      => $request->bank_card_number,
            'bank_name'             => $request->bank_name,
            'bank_province'         => $request->bank_province,
            'bank_city'             => $request->bank_city,
        ]);

        return BankCardResource::make($bankCard);
    }

    public function update(Request $request, BankCard $bankCard)
    {
        abort_if($bankCard->user_id !== auth()->user()->getKey(), Response::HTTP_NOT_FOUND);

        $this->validate($request, [
            'bank_card_holder_name' => 'nullable|string',
            'bank_card_number'      => 'nullable|numeric',
            'bank_name'             => 'nullable|string',
            'bank_province'         => 'nullable|string',
            'bank_city'             => 'nullable|string',
        ]);

        abort_if(
            !$bankCard->update([
                'bank_card_holder_name' => $request->bank_card_holder_name ?? $bankCard->bank_card_holder_name,
                'bank_card_number'      => $request->bank_card_number ?? $bankCard->bank_card_number,
                'bank_name'             => $request->bank_name ?? $bankCard->bank_name,
                'bank_province'         => $request->bank_province ?? $bankCard->bank_province,
                'bank_city'             => $request->bank_city ?? $bankCard->bank_city,
            ]),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        return BankCardResource::make($bankCard);
    }

    public function destroy(BankCard $bankCard)
    {
        abort_if($bankCard->user_id !== auth()->user()->getKey(), Response::HTTP_NOT_FOUND);

        abort_if(
            false === $bankCard->delete(),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
