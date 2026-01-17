<?php

namespace App\Http\Controllers\Exchange;

use App\Exceptions\RaceConditionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Exchange\MatchingDepositCollection;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Utils\AtomicLockUtil;
use App\Utils\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class MatchingDepositController extends Controller
{

    public function index()
    {
        if (
            !auth()->user()->deposit_enable
            || !auth()->user()->paufen_deposit_enable
        ) {
            return MatchingDepositCollection::make([]);
        }

        $matchingDeposits = Cache::get('available_matching_deposits_'.auth()->user()->getKey());

        if (null === $matchingDeposits) {
            $transactionGroups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
                ->whereIn('worker_id', User::whereAncestorOrSelf(auth()->user())->select(['id']));
            $currentProviderInTransactionGroup = $transactionGroups->exists();

            $matchingDeposits = Transaction::where('type', Transaction::TYPE_PAUFEN_WITHDRAW)
                ->when($currentProviderInTransactionGroup,
                    function (Builder $matchingDeposits) use ($transactionGroups) {
                        $matchingDeposits->whereIn('from_id', $transactionGroups->select('owner_id'));
                    })
                ->when(!$currentProviderInTransactionGroup, function (Builder $matchingDeposits) {
                    $matchingDeposits->whereDoesntHave('from.matchingDepositGroups');
                })
                ->where('status', Transaction::STATUS_MATCHING)
                ->where('created_at', '>=', now()->subDay())
                ->whereNull('locked_at')
                ->whereNull('to_id')
                ->oldest()
                ->limit(10)
                ->get();

            Cache::put('available_matching_deposits_'.auth()->user()->getKey(), $matchingDeposits,
                now()->addSeconds(5));
        }

        return MatchingDepositCollection::make($matchingDeposits);
    }

    public function update(
        Transaction $matchingDeposit,
        TransactionFactory $transactionFactory,
        AtomicLockUtil $atomicLockUtil
    ) {
        abort_if(
            !auth()->user()->deposit_enable
            || !auth()->user()->paufen_deposit_enable,
            Response::HTTP_BAD_REQUEST,
            '此功能暂时无法使用'
        );

        abort_if(
            $matchingDeposit->locked,
            Response::HTTP_BAD_REQUEST,
            '此笔订单已失效，请刷新'
        );


        $currentProviderInTransactionGroup = TransactionGroup::where('transaction_type',
            Transaction::TYPE_PAUFEN_WITHDRAW)
            ->whereIn('worker_id', User::whereAncestorOrSelf(auth()->user())->select(['id']))
            ->exists();

        $matchingDepositBelongsToCurrentProviderTransactionGroup = TransactionGroup::where('transaction_type',
            Transaction::TYPE_PAUFEN_WITHDRAW)
            ->where('owner_id', $matchingDeposit->from_id)
            ->whereIn('worker_id', User::whereAncestorOrSelf(auth()->user())->select(['id']))
            ->exists();

        $matchingDepositNotBelongsToAnyTransactionGroup = !TransactionGroup::where('transaction_type',
            Transaction::TYPE_PAUFEN_WITHDRAW)
            ->where('owner_id', $matchingDeposit->from_id)
            ->exists();

        abort_if(
            $currentProviderInTransactionGroup
            && !$matchingDepositBelongsToCurrentProviderTransactionGroup,
            Response::HTTP_BAD_REQUEST,
            '无法抢单，请刷新后重试'
        );

        abort_if(
            !$currentProviderInTransactionGroup
            && !$matchingDepositNotBelongsToAnyTransactionGroup,
            Response::HTTP_BAD_REQUEST,
            '无法抢单，请刷新后重试'
        );

        $callback = function () use ($transactionFactory, $matchingDeposit) {
            abort_if(
                Transaction::where([
                    'to_id' => auth()->user()->getKey(),
                    'type'  => Transaction::TYPE_PAUFEN_WITHDRAW,
                ])
                    ->where(function (Builder $transactions) {
                        $transactions->where(function (Builder $transactions) {
                            $transactions->whereIn('status',
                                [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                                ->where('to_wallet_settled', false);
                        })->orWhereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]);
                    })
                    ->where('created_at', '>=', now()->subDay())
                    ->exists(),
                Response::HTTP_BAD_REQUEST,
                '请先完成前一笔买入'
            );

            try {
                $transactionFactory->paufenDepositTo(auth()->user(), $matchingDeposit);
            } catch (RaceConditionException $raceConditionException) {
                abort(Response::HTTP_BAD_REQUEST, '冲突，请刷新后重试');
            }

            return $matchingDeposit;
        };

        $matchingDeposit = $atomicLockUtil->lock($atomicLockUtil->keyForUserDeposit(auth()->user()), $callback);

        Cache::put('admin_deposits_added_at', now(), now()->addSeconds(60));

        return \App\Http\Resources\Exchange\Transaction::make($matchingDeposit->refresh());
    }
}
