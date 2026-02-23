<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\TransactionStatCollection;
use App\Models\User;
use App\Repository\UserTransactionStatRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Kalnoy\Nestedset\NestedSet;

class TransactionStatController extends Controller
{
    public function __construct(
        private readonly UserTransactionStatRepository $statRepository
    ) {}

    public function index(Request $request)
    {
        $parentId = auth()->user()->getKey();

        $this->validate($request, [
            'parent_id' => 'nullable|in:'.$parentId,
        ]);

        if ($request->parent_id) {
            $users = User::where('parent_id', $request->parent_id)
                ->where('role', User::ROLE_PROVIDER)
                ->get(['id', 'name', NestedSet::PARENT_ID, NestedSet::LFT, NestedSet::RGT]);
        } else {
            $users = User::where('id', $parentId)->get(['id', 'name', NestedSet::PARENT_ID, NestedSet::LFT, NestedSet::RGT]);
        }

        $selfTransactionStats = $this->statRepository->getSelfTransactionStats($users->pluck('id'), 'from_id', 'floating_amount');

        $today = Carbon::today()->format('Y-m-d');
        $yesterday = Carbon::yesterday()->format('Y-m-d');

        $users = $users->map(function (User $user) use ($selfTransactionStats, $today, $yesterday) {
            $user->setAttribute('yesterday_self_total', data_get($selfTransactionStats, "{$user->getKey()}_$yesterday", '0.00'));
            $user->setAttribute('today_self_total', data_get($selfTransactionStats, "{$user->getKey()}_$today", '0.00'));

            $descendantIds = $user->descendants()->pluck('id');

            $descendantTransactionStats = $this->statRepository->getDescendantTransactionStats($descendantIds, 'from_id', 'floating_amount');

            $user->setAttribute('yesterday_descendants_total', data_get($descendantTransactionStats, $yesterday, '0.00'));
            $user->setAttribute('today_descendants_total', data_get($descendantTransactionStats, $today, '0.00'));

            return $user;
        });

        return TransactionStatCollection::make($users);
    }
}
