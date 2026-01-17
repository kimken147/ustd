<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\SystemBankCardCollection;
use App\Models\Permission;
use App\Models\SystemBankCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SystemBankCardController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:' . Permission::ADMIN_CREATE_SYSTEM_BANK_CARD])->only('store');
        $this->middleware(['permission:' . Permission::ADMIN_UPDATE_SYSTEM_BANK_CARD])->only('update');
        $this->middleware(['permission:' . Permission::ADMIN_DESTROY_SYSTEM_BANK_CARD])->only('destroy');
    }

    public function destroy(SystemBankCard $systemBankCard)
    {
        DB::transaction(function () use ($systemBankCard) {
            $systemBankCard->delete();

            $systemBankCard->users()->detach();
        });

        return response()->noContent();
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            'ids'        => 'nullable|array',
            'q'          => 'nullable|string',
            'statuses'   => 'nullable|array',
            'statuses.*' => [
                'nullable', 'integer',
                Rule::in(SystemBankCard::STATUS_UNPUBLISHED, SystemBankCard::STATUS_PUBLISHED)
            ],
            'user_id'    => 'nullable|integer',
        ]);

        $systemBankCards = SystemBankCard::orderBy('status', 'desc')->latest();

        $systemBankCards->when($request->ids, function (Builder $bankCards, $ids) use ($request) {
            $bankCards->whereIn('id', $ids);

            $request->merge([
                'no_paginate' => 1,
            ]);
        });

        $systemBankCards->when($request->has('q'), function (Builder $bankCards) use ($request) {
            $bankCards->where(function (Builder $bankCards) use ($request) {
                $q = $request->q;

                $bankCards->where('bank_card_holder_name', 'like', "%$q%")
                    ->orWhere('bank_card_number', $q)
                    ->orWhere('bank_name', 'like', "%$q%");
            });
        });

        $systemBankCards->when($request->statuses, function (Builder $bankCards, $status) {
            $bankCards->whereIn('status', $status);
        });

        $systemBankCards->when($request->note, function (Builder $bankCards) use ($request) {
            $bankCards->where("note", "like", "%$request->note%");
        });

        $systemBankCards->when($request->user_id, function (Builder $bankCards, $userId) {
            /** @var User $user */
            $user = User::findOrFail($userId);

            $bankCards->where(function (Builder $bankCards) use ($user) {
                $bankCards->whereHas('nonShareDescendantsUsers', function (Builder $users) use ($user) {
                    $users->where('users.id', $user->getKey());
                });

                $root = $user->isRoot() ? $user : User::whereIsRoot()->whereAncestorOf($user->getKey())->first();

                $bankCards->orWhereHas('shareDescendantsUsers', function (Builder $users) use ($root) {
                    $users->where('users.id', $root->getKey());
                });
            });
        });

        return SystemBankCardCollection::make(
            $request->boolean('no_paginate') ? $systemBankCards->get() : $systemBankCards->paginate(20)
        );
    }

    public function show(SystemBankCard $systemBankCard)
    {
        return \App\Http\Resources\Admin\SystemBankCard::make($systemBankCard);
    }

    public function store(Request $request)
    {
        // todo 取消 user_ids 的設計
        $this->validate($request, [
            'system_bank_cards.*.bank_name'                 => 'required|string|max:50',
            'system_bank_cards.*.bank_province'             => 'nullable|string|max:50',
            'system_bank_cards.*.bank_city'                 => 'nullable|string|max:50',
            'system_bank_cards.*.bank_card_holder_name'     => 'required|string|max:50',
            'system_bank_cards.*.bank_card_number'          => 'required|string|max:50',
            'system_bank_cards.*.users'                     => 'nullable|array',
            'system_bank_cards.*.users.*.id'                => 'required_with:users|int',
            'system_bank_cards.*.users.*.share_descendants' => 'required_with:users|boolean',
            'system_bank_cards.*.user_ids'                  => 'nullable|array',
            'system_bank_cards.*.user_ids.*'                => 'required_with:user_ids|int',
        ]);

        DB::transaction(function () use ($request) {
            foreach ($request->input('system_bank_cards', []) as $systemBankCardData) {
                /** @var SystemBankCard $systemBankCard */
                $systemBankCard = SystemBankCard::create($systemBankCardData);

                $userIds = data_get($systemBankCardData, 'user_ids');

                if (!empty($userIds)) {
                    $systemBankCard->users()->sync($userIds);
                }

                if ($users = data_get($systemBankCardData, 'users', [])) {
                    $users = collect($users)->mapWithKeys(function ($user) {
                        return [$user['id'] => ['share_descendants' => $user['share_descendants']]];
                    });

                    $systemBankCard->users()->sync($users);
                }
            }
        });

        return response()->json(null, Response::HTTP_CREATED);
    }

    public function update(Request $request, SystemBankCard $systemBankCard)
    {
        $this->validate($request, [
            'bank_name'                 => 'string|max:50',
            'bank_province'             => 'nullable|string|max:50',
            'bank_city'                 => 'nullable|string|max:50',
            'bank_card_holder_name'     => 'string|max:50',
            'bank_card_number'          => 'string|max:50',
            'note'          => 'string|max:50',
            'user_ids'                  => 'array',
            'user_ids.*'                => 'required_with:user_ids|int',
            'users'                     => 'array',
            'users.*.id'                => 'required_with:users|int',
            'users.*.share_descendants' => 'required_with:users|boolean',
            'status'                    => [
                'int', Rule::in(SystemBankCard::STATUS_UNPUBLISHED, SystemBankCard::STATUS_PUBLISHED),
            ],
            'balance'                   => [
                Rule::requiredIf($request->status === SystemBankCard::STATUS_PUBLISHED), 'numeric',
            ],
        ]);

        abort_if(
            $systemBankCard->status === SystemBankCard::STATUS_UNPUBLISHED
                && $request->status !== SystemBankCard::STATUS_PUBLISHED
                && $request->balance,
            Response::HTTP_BAD_REQUEST,
            __('system-bank-card.Only published can update balance')
        );

        $systemBankCard = DB::transaction(function () use ($request, $systemBankCard) {
            if ($request->status === SystemBankCard::STATUS_PUBLISHED) {
                $systemBankCard->status = SystemBankCard::STATUS_PUBLISHED;
                $systemBankCard->balance = $request->balance;
                $systemBankCard->published_at = now();
            }

            if ($request->status === SystemBankCard::STATUS_UNPUBLISHED) {
                $systemBankCard->status = SystemBankCard::STATUS_UNPUBLISHED;
                $systemBankCard->balance = 0;
                $systemBankCard->published_at = null;
            }

            $systemBankCard->fill(
                $request->only('bank_name', 'bank_province', 'bank_city', 'bank_card_holder_name', 'bank_card_number', 'balance', "note")
            );

            $systemBankCard->save();

            if (!empty($request->user_ids)) {
                $systemBankCard->users()->sync($request->user_ids);
            }

            if ($request->users) {
                $users = collect($request->users)->mapWithKeys(function ($user) {
                    return [$user['id'] => ['share_descendants' => $user['share_descendants']]];
                });

                $systemBankCard->users()->sync($users);
            }

            return $systemBankCard;
        });

        return \App\Http\Resources\Admin\SystemBankCard::make($systemBankCard);
    }
}
