<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\BankCard as BankCardResource;
use App\Http\Resources\Provider\BankCardCollection;
use App\Models\BankCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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

    public function store(Request $request)
    {
        $this->validate($request, [
            'bank_card_holder_name' => 'nullable|string',
            'bank_card_number'      => 'nullable|numeric',
            'bank_name'             => 'nullable|string',
        ]);

        $bankCard = BankCard::create([
            'user_id'               => auth()->user()->getKey(),
            'status'                => BankCard::STATUS_REVIEW_PASSED,
            'bank_card_holder_name' => $request->bank_card_holder_name,
            'bank_card_number'      => $request->bank_card_number,
            'bank_name'             => $request->bank_name,
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
        ]);

        abort_if(
            !$bankCard->update([
                'bank_card_holder_name' => $request->bank_card_holder_name ?? $bankCard->bank_card_holder_name,
                'bank_card_number'      => $request->bank_card_number ?? $bankCard->bank_card_number,
                'bank_name'             => $request->bank_name ?? $bankCard->bank_name,
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
