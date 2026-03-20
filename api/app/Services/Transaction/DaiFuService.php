<?php

namespace App\Services\Transaction;

use App\Models\Channel;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Utils\TransactionMutator;

class DaiFuService
{
    private TransactionMutator $transactionMutator;
    private FeatureToggleRepository $featureToggleRepository;
    public function __construct(TransactionMutator $transactionMutator, FeatureToggleRepository $featureToggleRepository)
    {
        $this->transactionMutator = $transactionMutator;
        $this->featureToggleRepository = $featureToggleRepository;
    }

    public function execute(string $channelCode)
    {
        $isUsdt = Channel::isUsdt($channelCode);

        // 1. 取得所有可用出款帳號
        if ($isUsdt) {
            $accounts = UserChannelAccount::with('user')
                ->where('channel_code', $channelCode)
                ->where('status', UserChannelAccount::STATUS_ONLINE)
                ->where('is_auto', true)
                ->whereDoesntHave('payingDaifu')
                ->where(function ($q) {
                    $q->where(function ($q2) {
                        $q2->where('address_type', UserChannelAccount::ADDRESS_TYPE_MASTER)
                           ->where('type', '!=', UserChannelAccount::TYPE_DEPOSIT);
                    })
                    ->orWhere(function ($q2) {
                        $q2->where('address_type', UserChannelAccount::ADDRESS_TYPE_CHILD)
                           ->where('receive_status', UserChannelAccount::RECEIVE_STATUS_USED);
                    });
                })
                ->where('onchain_usdt_balance', '>', 0)
                ->get();
        } else {
            $accounts = UserChannelAccount::with('user')
                ->where('channel_code', $channelCode)
                ->where('status', UserChannelAccount::STATUS_ONLINE)
                ->where('type', '!=', UserChannelAccount::TYPE_DEPOSIT)
                ->where('is_auto', true)
                ->whereDoesntHave('payingDaifu')
                ->orderByDesc('type')
                ->orderBy('balance')
                ->get();
        }

        // 非 USDT：保持原有邏輯（以帳號為主迴圈）
        if (!$isUsdt) {
            $this->matchByAccount($accounts, false);
            return;
        }

        // 2. USDT：以待付單為主，找餘額最接近的帳號（減少出款次數）
        $this->matchByTransaction($accounts);
    }

    /**
     * USDT 配單：以待付單為主，找鏈上餘額最接近（>=）出款金額的帳號
     * 優先讓餘額剛好夠的帳號出款，減少鏈上交易次數
     */
    private function matchByTransaction($accounts): void
    {
        // 過濾出可用帳號及其用戶權限
        $validAccounts = $accounts->filter(function ($account) {
            $user = $account->user;
            return $user && $user->deposit_enable && $user->paufen_deposit_enable;
        });

        if ($validAccounts->isEmpty()) {
            return;
        }

        // 取得所有待配對的代付單
        $pendingWithdraws = $this->getPendingWithdraws($validAccounts);

        // 已配對的帳號 ID（避免重複配對）
        $matchedAccountIds = [];

        foreach ($pendingWithdraws as $withdraw) {
            $amount = (float) $withdraw->amount;

            // 在可用帳號中找餘額最接近且足夠的（排除已配對的）
            $bestAccount = null;
            $bestDiff = PHP_FLOAT_MAX;

            foreach ($validAccounts as $account) {
                if (in_array($account->id, $matchedAccountIds)) {
                    continue;
                }

                $balance = (float) $account->onchain_usdt_balance;
                if ($balance < $amount) {
                    continue;
                }

                // 檢查用戶的代付金額限制
                $user = $account->user;
                $minAmount = (float) ($user->wallet->agency_withdraw_min_amount ?? 0);
                $maxAmount = (float) ($user->wallet->agency_withdraw_max_amount ?? 0);
                if ($minAmount > 0 && $amount < $minAmount) continue;
                if ($maxAmount > 0 && $amount > $maxAmount) continue;

                // 檢查交易組權限
                if (!$this->canMatchWithdraw($account, $withdraw)) {
                    continue;
                }

                $diff = $balance - $amount;
                if ($diff < $bestDiff) {
                    $bestDiff = $diff;
                    $bestAccount = $account;
                }
            }

            if ($bestAccount) {
                $this->transactionMutator->paufenDepositToAccount($bestAccount, $withdraw);
                $matchedAccountIds[] = $bestAccount->id;
            }
        }
    }

    /**
     * 非 USDT：保持原有以帳號為主的配單邏輯
     */
    private function matchByAccount($accounts, bool $isUsdt): void
    {
        foreach ($accounts as $account) {
            $user = $account->user;

            if (!$user->deposit_enable || !$user->paufen_deposit_enable) {
                continue;
            }

            $availableBalance = $account->getRestBalance('withdraw') ?? 0;

            $transactionGroups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
                ->where(function ($query) use ($user) {
                    $query->where('worker_id', $user->id)->where('personal_enable', false);
                    $query->orWhereIn('worker_id', User::whereAncestorOrSelf($user)->select(['id']))->where('personal_enable', true);
                });

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
                ->when((float) $user->wallet->agency_withdraw_min_amount > 0, function ($builder) use ($user) {
                    $builder->where('amount', '>=', $user->wallet->agency_withdraw_min_amount);
                })
                ->when((float) $user->wallet->agency_withdraw_max_amount > 0, function ($builder) use ($user) {
                    $builder->where('amount', '<=', $user->wallet->agency_withdraw_max_amount);
                })
                ->where('amount', '<=', $availableBalance)
                ->whereNull('locked_at')
                ->whereNull('to_id')
                ->where('created_at', '>=', now()->subDay())
                ->oldest()
                ->first();

            if (!$matchingDeposit) {
                continue;
            }
            $this->transactionMutator->paufenDepositToAccount($account, $matchingDeposit);
        }
    }

    /**
     * 取得所有待配對的代付單（按金額降序，大單優先配對）
     */
    private function getPendingWithdraws($accounts): \Illuminate\Database\Eloquent\Collection
    {
        // 收集所有帳號對應的 transaction group owner IDs
        $allOwnerIds = collect();
        foreach ($accounts as $account) {
            $user = $account->user;
            $transactionGroups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
                ->where(function ($query) use ($user) {
                    $query->where('worker_id', $user->id)->where('personal_enable', false);
                    $query->orWhereIn('worker_id', User::whereAncestorOrSelf($user)->select(['id']))->where('personal_enable', true);
                });

            if ($transactionGroups->exists()) {
                $allOwnerIds = $allOwnerIds->merge($transactionGroups->pluck('owner_id'));
            }
        }

        return Transaction::where(function ($builder) use ($allOwnerIds) {
            $builder->where(function ($query) use ($allOwnerIds) {
                $query->where('type', Transaction::TYPE_PAUFEN_WITHDRAW);
                if ($allOwnerIds->isNotEmpty()) {
                    $query->whereIn('from_id', $allOwnerIds->unique());
                } else {
                    $query->whereDoesntHave('from.matchingDepositGroups');
                }
            })->orWhere(function ($query) {
                $query->where('type', Transaction::TYPE_INTERNAL_TRANSFER);
            });
        })
            ->where('status', Transaction::STATUS_MATCHING)
            ->whereNull('locked_at')
            ->whereNull('to_id')
            ->where('created_at', '>=', now()->subDay())
            ->orderByDesc('amount') // 大單優先配對
            ->get();
    }

    /**
     * 檢查帳號是否有權限配對此代付單
     */
    private function canMatchWithdraw(UserChannelAccount $account, Transaction $withdraw): bool
    {
        $user = $account->user;
        $transactionGroups = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_WITHDRAW)
            ->where(function ($query) use ($user) {
                $query->where('worker_id', $user->id)->where('personal_enable', false);
                $query->orWhereIn('worker_id', User::whereAncestorOrSelf($user)->select(['id']))->where('personal_enable', true);
            });

        if ($transactionGroups->exists()) {
            return $transactionGroups->pluck('owner_id')->contains($withdraw->from_id);
        }

        // 無交易組限制時，只要商戶也無交易組就可配
        return !$withdraw->from?->matchingDepositGroups()?->exists();
    }

    public function checkAutoIsValid(string $region)
    {
        if (
            config('app.region') != $region ||
            !$this->featureToggleRepository->enabled(FeatureToggle::AUTO_DAIFU, true) ||
            $this->featureToggleRepository->valueOf(FeatureToggle::AUTO_DAIFU) != 2
        ) {
            return false;
        }
        return true;
    }
}
