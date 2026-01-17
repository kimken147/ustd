<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemBankCard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class BatchUpdateSystemBankCardController extends Controller
{

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function __invoke(Request $request)
    {
        $this->validate($request, [
            'status'                      => [
                'required', 'integer', Rule::in(SystemBankCard::STATUS_UNPUBLISHED, SystemBankCard::STATUS_PUBLISHED)
            ],
            'system_bank_cards'           => 'array',
            'system_bank_cards.*.id'      => 'required_with:system_bank_cards|integer',
            'system_bank_cards.*.balance' => [
                'numeric', Rule::requiredIf($request->status === SystemBankCard::STATUS_PUBLISHED),
            ],
        ]);

        $systemBankCardCount = SystemBankCard::whereIn('id', Arr::pluck($request->system_bank_cards, 'id'))->count();

        abort_if(
            $systemBankCardCount !== count($request->system_bank_cards),
            Response::HTTP_BAD_REQUEST,
            '无对应系统银行卡'
        );

        $now = now();

        $systemBankCardValues = collect($request->system_bank_cards)->map(function ($systemBankCard) use (
            $request,
            $now
        ) {
            return [
                'id'                    => $systemBankCard['id'],
                'bank_card_holder_name' => '123', // don't care
                'bank_card_number'      => '123', // don't care
                'bank_name'             => '123', // don't care
                'status'                => $request->status,
                'balance'               => $request->status === SystemBankCard::STATUS_PUBLISHED ? $systemBankCard['balance'] : 0,
                'updated_at'            => $now,
                'published_at'          => $request->status === SystemBankCard::STATUS_PUBLISHED ? $now : null,
            ];
        });

        SystemBankCard::insertOnDuplicateKey($systemBankCardValues->values()->toArray(),
            ['status', 'balance', 'updated_at', 'published_at']); // please be aware of updating bank info

        return response()->noContent(Response::HTTP_CREATED);
    }
}
