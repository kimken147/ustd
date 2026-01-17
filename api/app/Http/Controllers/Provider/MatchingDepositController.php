<?php

namespace App\Http\Controllers\Provider;

use App\Exceptions\RaceConditionException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Provider\Deposit;
use App\Http\Resources\Provider\MatchingDeposit;
use App\Http\Resources\Provider\MatchingDepositCollection;
use App\Repository\FeatureToggleRepository;
use App\Models\SystemBankCard;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\FeatureToggle;
use App\Models\UserChannelAccount;
use App\Utils\AtomicLockUtil;
use App\Utils\TransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use App\Utils\BCMathUtil;

class MatchingDepositController extends Controller
{

    public function index(
        Request $request,
        FeatureToggleRepository $featureToggleRepository
    ) {
        $user = auth()->user();
        if (!$user->deposit_enable || !$user->paufen_deposit_enable) {
            return MatchingDepositCollection::make([]);
        }

        $transactionGroups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
            ->where(function ($query) use ($user) {
                $query->where('worker_id', $user->id)->where('personal_enable', false);
                $query->orWhereIn('worker_id', User::whereAncestorOrSelf($user)->select(['id']))->where('personal_enable', true);
            });
        $currentProviderInTransactionGroup = $transactionGroups->exists();

        $matchingDeposits = Transaction::where('type', Transaction::TYPE_PAUFEN_WITHDRAW)
            ->when($currentProviderInTransactionGroup, function (Builder $matchingDeposits) use ($transactionGroups) {
                $matchingDeposits->whereIn('from_id', $transactionGroups->select('owner_id'));
            })
            ->when(!$currentProviderInTransactionGroup, function (Builder $matchingDeposits) {
                $matchingDeposits->whereDoesntHave('from.matchingDepositGroups');
            })
            ->when($user->wallet->agency_withdraw_min_amount, function ($builder) use ($user) {
                $builder->where('amount', '>=', $user->wallet->agency_withdraw_min_amount);
            })
            ->when($user->wallet->agency_withdraw_max_amount, function ($builder) use ($user) {
                $builder->where('amount', '<=', $user->wallet->agency_withdraw_max_amount);
            })
            ->when($request->account_id, function ($builder, $accountId) {
                $account = UserChannelAccount::where(['id' => $accountId, 'user_id' => auth()->id()])->first();
                $builder->where('amount', '<=', $account->getRestBalance('withdraw'));
            })
            ->where('status', Transaction::STATUS_MATCHING)
            ->where('created_at', '>=', now()->subMonth())
            ->whereNull('locked_at')
            ->whereNull('to_id')
            ->oldest()
            ->get();

        return MatchingDepositCollection::make($matchingDeposits);
    }

    public function update(
        Request $request,
        Transaction $matchingDeposit,
        TransactionFactory $transactionFactory,
        AtomicLockUtil $atomicLockUtil,
        FeatureToggleRepository $featureToggleRepository
    ) {
        abort_if(
            !auth()->user()->deposit_enable
                || !auth()->user()->paufen_deposit_enable,
            Response::HTTP_BAD_REQUEST,
            __('user.Deposit disabled')
        );

        abort_if(
            $matchingDeposit->locked,
            Response::HTTP_BAD_REQUEST,
            __('transaction.Updating to matching deposit failed')
        );


        $currentProviderInTransactionGroup = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
            ->whereIn('worker_id', User::whereAncestorOrSelf(auth()->user())->select(['id']))
            ->exists();

        $matchingDepositBelongsToCurrentProviderTransactionGroup = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
            ->where('owner_id', $matchingDeposit->from_id)
            ->whereIn('worker_id', User::whereAncestorOrSelf(auth()->user())->select(['id']))
            ->exists();

        $matchingDepositNotBelongsToAnyTransactionGroup = !TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
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

        if ($request->has('account_id')) {
            $account = UserChannelAccount::where(['id' => $request->account_id, 'user_id' => auth()->id()])->first();
            abort_if($account->getRestBalance('withdraw') <= $matchingDeposit->amount, Response::HTTP_BAD_REQUEST, '出款帐号余额不足');
        }

        $depositCheckQuery = Transaction::where('to_id', auth()->user()->getKey())
            ->where('type', Transaction::TYPE_PAUFEN_WITHDRAW)
            ->where(function (Builder $transactions) {
                $transactions->where(function (Builder $transactions) {
                    $transactions->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                        ->where('to_wallet_settled', false);
                })->orWhereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]);
            })
            ->where('created_at', '>=', now()->subDay());

        abort_if(
            $featureToggleRepository->enabled(FeatureToggle::MAX_PROVIDER_HIGH_QUALITY_DEPOSIT_COUNT) &&
                $depositCheckQuery->count() >= $featureToggleRepository->valueOf(FeatureToggle::MAX_PROVIDER_HIGH_QUALITY_DEPOSIT_COUNT, 1),
            Response::HTTP_BAD_REQUEST,
            __('transaction.Please complete previous deposit')
        );

        $callback = function () use ($transactionFactory, $matchingDeposit, $request) {
            try {
                if ($request->has('account_id')) {
                    $account = UserChannelAccount::find($request->account_id);
                    $transactionFactory->paufenDepositToAccount($account, $matchingDeposit);
                } else {
                    $transactionFactory->paufenDepositTo(auth()->user(), $matchingDeposit);
                }
            } catch (RaceConditionException $raceConditionException) {
                abort(Response::HTTP_BAD_REQUEST, __('transaction.Updating to matching deposit failed'));
            }

            return $matchingDeposit;
        };

        $matchingDeposit = $atomicLockUtil->lock($atomicLockUtil->keyForUserDeposit(auth()->user()), $callback);

        Cache::put('admin_deposits_added_at', now(), now()->addSeconds(60));

        $matchingDeposit->load(['certificateFiles']);
        return Deposit::make($matchingDeposit->refresh());
    }
}
