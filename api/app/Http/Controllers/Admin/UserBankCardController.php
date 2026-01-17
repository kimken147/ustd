<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\UserBankCard;
use App\Http\Resources\Admin\UserBankCardCollection;
use App\Model\BankCard;
use App\Model\Permission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class UserBankCardController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:'.Permission::ADMIN_UPDATE_USER_BANK_CARD])->only('update');
        $this->middleware(['permission:'.Permission::ADMIN_DESTROY_USER_BANK_CARD])->only('destroy');
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            'status' => 'nullable|array',
        ]);

        /** @var Builder $bankCards */
        $bankCards = BankCard::has('user')->where('user_id', '>', BankCard::SYSTEM_USER_ID)
            ->with('user')
            ->latest('id');

        $bankCards->when($request->name_or_username, function (Builder $bankCards, $nameOrUsername) {
            $bankCards->whereHas('user', function (Builder $users) use ($nameOrUsername) {
                $users->where('name', 'like', "%$nameOrUsername%")
                    ->orWhere('username', $nameOrUsername);
            });
        });

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

        return UserBankCardCollection::make($bankCards->paginate(20));
    }

    public function update(Request $request, BankCard $userBankCard)
    {
        abort_if($userBankCard->user_id === 0, Response::HTTP_NOT_FOUND);

        $this->validate($request, [
            'status' => [
                'required', 'int', Rule::in([
                    BankCard::STATUS_REVIEWING, BankCard::STATUS_REVIEW_PASSED, BankCard::STATUS_REVIEW_REJECTED,
                ])
            ]
        ]);

        abort_if(
            !$userBankCard->update(['status' => $request->status]),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        return UserBankCard::make($userBankCard);
    }

    public function destroy(BankCard $userBankCard)
    {
        abort_if($userBankCard->user_id === 0, Response::HTTP_NOT_FOUND);

        abort_if(
            false === $userBankCard->delete(),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
