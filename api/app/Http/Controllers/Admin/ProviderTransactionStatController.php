<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ProviderTransactionStatCollection;
use App\Models\User;
use App\Repository\UserTransactionStatRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Kalnoy\Nestedset\NestedSet;

class ProviderTransactionStatController extends Controller
{
    public function __construct(
        private readonly UserTransactionStatRepository $statRepository
    ) {}

    public function index(Request $request)
    {
        $this->validate($request, [
            'parent_id' => 'nullable',
        ]);

        $users = User::where('parent_id', $request->parent_id)
            ->where('role', User::ROLE_PROVIDER)
            ->get(['id', 'name', NestedSet::PARENT_ID, NestedSet::LFT, NestedSet::RGT]);

        $selfTransactionStats = $this->statRepository->getSelfTransactionStats($users->pluck('id'), 'from_id');

        $today = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        $users = $users->map(function (User $user) use ($selfTransactionStats, $today, $yesterday) {
            $user->setAttribute('yesterday_self_total', data_get($selfTransactionStats, "{$user->getKey()}_$yesterday", '0.00'));
            $user->setAttribute('today_self_total', data_get($selfTransactionStats, "{$user->getKey()}_$today", '0.00'));

            $descendantIds = $user->descendants()->pluck('id');
            $descendantsAndSelf = $descendantIds->merge($user->getKey());

            $descendantTransactionStats = $this->statRepository->getDescendantTransactionStats($descendantIds, 'from_id');
            $balanceTotal = $this->statRepository->getWalletBalanceTotal($descendantsAndSelf);

            $user->setAttribute('yesterday_descendants_total', data_get($descendantTransactionStats, $yesterday, '0.00'));
            $user->setAttribute('today_descendants_total', data_get($descendantTransactionStats, $today, '0.00'));
            $user->setAttribute('descendants_total', $descendantIds->count());
            $user->setAttribute('balance_total', $balanceTotal);

            return $user;
        });

        return ProviderTransactionStatCollection::make($users);
    }
}
