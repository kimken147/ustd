<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

use App\Model\Channel;
use App\Model\UserChannelAccount;
use App\Utils\TransactionUtil;
use App\Model\TransactionGroup;
use App\Model\Transaction;
use App\Model\User;
use App\Utils\TransactionFactory;
use App\Jobs\GcashDaifu;
use App\Repository\FeatureToggleRepository;
use App\Model\FeatureToggle;

class ProcessGcashAutoDaifu extends Command
{

    /**
     * @var string
     */
    protected $description = '執行 GCash 自動代付';
    /**
     * @var string
     */
    protected $signature = 'gcash:auto-daifu';

    public function handle(TransactionFactory $transactionFactory, FeatureToggleRepository $featureToggleRepository)
    {
        if (config('app.region') != 'ph' ||
            !$featureToggleRepository->enabled(FeatureToggle::AUTO_DAIFU) ||
            $featureToggleRepository->valueOf(FeatureToggle::AUTO_DAIFU) != 2) {
            return;
        }

        // 查出目前未有付款中代付單 的出款帳號
        $accounts = UserChannelAccount::with('user')
            ->where('channel_code', Channel::CODE_GCASH)
            ->where('status', UserChannelAccount::STATUS_ONLINE)
            ->where('type', '!=', UserChannelAccount::TYPE_DEPOSIT)
            ->where('is_auto', true)
            ->whereDoesntHave('payingDaifu')
            ->orderByDesc('type')
            ->orderBy('balance')
            ->get();

        foreach ($accounts as $account) {
            $user = $account->user;

            if (!$user->deposit_enable || !$user->paufen_deposit_enable) {
                continue;
            }

            $transactionGroups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
                ->where(function ($query) use($user) {
                    $query->where('worker_id',$user->id)->where('personal_enable',false);
                    $query->orWhereIn('worker_id', User::whereAncestorOrSelf($user)->select(['id']))->where('personal_enable',true);
                });

            $transactionGroupExists = $transactionGroups->exists();

            // 查出該出款帳號，目前可以代付的代付單
            $matchingDeposit = Transaction::where(function ($builder) use ($transactionGroups) {
                    $builder->where(function ($query) use ($transactionGroups) {
                        $query->where('type', Transaction::TYPE_PAUFEN_WITHDRAW)
                            ->when($transactionGroups->exists(), function ($matchingDeposits) use ($transactionGroups) {
                                $matchingDeposits->whereIn('from_id', $transactionGroups->select('owner_id'));
                            })
                            ->when(!$transactionGroups->exists(), function ($matchingDeposits) {
                                $matchingDeposits->whereDoesntHave('from.matchingDepositGroups');
                            });
                    })->orWhere(function ($query) {
                        $query->where('type', Transaction::TYPE_INTERNAL_TRANSFER);
                    });
                })
                ->where('status', Transaction::STATUS_MATCHING)
                ->when($user->wallet->agency_withdraw_min_amount, function ($builder) use ($user) {
                    $builder->where('amount', '>=', $user->wallet->agency_withdraw_min_amount);
                })
                ->when($user->wallet->agency_withdraw_max_amount, function ($builder) use ($user) {
                    $builder->where('amount', '<=', $user->wallet->agency_withdraw_max_amount);
                })
                ->where('amount', '<=', $account->getRestBalance('withdraw') ?? 0)
                ->whereNull('locked_at')
                ->whereNull('to_id')
                ->where('created_at', '>=', now()->subDay())
                ->oldest()
                ->first();

            if (!$matchingDeposit) {
                continue;
            }
            // 分配出款帳號給代付單
            $transactionFactory->paufenDepositToAccount($account, $matchingDeposit);

            // 用異步Job的方式執行自動代付
            GcashDaifu::dispatch($matchingDeposit, 'init');
        }

        $transactions = Transaction::whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_INTERNAL_TRANSFER])
            ->where('status', Transaction::STATUS_PAYING)
            ->whereNull('locked_at')
            ->whereNotNull('to_channel_account_id')
            ->where('created_at', '>=', now()->subDay())
            ->get();

        foreach ($transactions as $transaction) {
            $status = data_get($transaction->to_channel_account, 'status');

            if ($status === 'need_mpin' && !data_get($transaction->to_channel_account, 'mpin_fail', false)) { // 檢查目前是否有 need_mpin 的代付單
                GcashDaifu::dispatch($transaction, 'mpin');
            }
            if ($status === 'check_tx') { // 檢查目前是否有 check_tx 的代付單
                GcashDaifu::dispatch($transaction, 'pay');
            }
            if ($status === 'need_otp') { // 檢查目前是否有 need_otp 的代付單
                GcashDaifu::dispatch($transaction, 'otp');
            }
        }
    }
}
