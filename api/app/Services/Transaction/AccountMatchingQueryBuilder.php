<?php

namespace App\Services\Transaction;

use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\TransactionGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\WhitelistedIpManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Stevebauman\Location\Facades\Location;

class AccountMatchingQueryBuilder
{
    public function __construct(
        private readonly BCMathUtil $bcMath,
        private readonly FeatureToggleRepository $featureToggleRepository,
        private readonly WhitelistedIpManager $whitelistedIpManager,
    ) {}

    public function findSuitableUserChannelAccounts(
        Transaction $transaction,
        Channel $channel,
        UserChannel $merchantUserChannel,
        ChannelAmount $channelAmount
    ): Collection {
        DB::enableQueryLog();

        $query = $this->initializeAccountQuery();

        $this->applyProviderConcurrentLimit($query);
        $this->applyBalanceLimits($query, $transaction);
        $this->applySingleTransactionLimits($query, $transaction);
        $this->applyFloatingAmountRestriction($query, $channel, $transaction);
        $this->applyPayingTransactionsRestriction($query, $transaction, $channel);
        $this->applyUserAndAccountStatus($query);
        $this->applyAccountType($query);
        $this->applyAddressTypeFilter($query);
        $this->applyChannelAmountAndFee($query, $channelAmount, $merchantUserChannel);
        $this->applyReadyForMatching($query);
        $this->applyTimeLimit($query);
        $this->applyWalletBalanceConditions($query, $transaction);
        $this->applyBankRestrictions($query, $channel);
        $this->applyTransactionGroupConditions($query, $transaction);
        $this->applyGeolocationMatching($query, $channel);
        $this->applyMatchingOrder($query);

        $providerUserChannelAccounts = $query->get(['user_channel_accounts.*']);

        if ($providerUserChannelAccounts->isEmpty()) {
            return collect();
        }

        $filteredAccounts = $this->filterAccountsByAmountRestrictions($providerUserChannelAccounts, $transaction);
        $matchedAccounts = $this->matchLastAccountIfRequested($filteredAccounts, $channel);

        return $this->replaceBankNames($matchedAccounts);
    }

    private function initializeAccountQuery()
    {
        return UserChannelAccount::query()
            ->with('bank', 'channelAmount')
            ->join('users', 'users.id', '=', 'user_channel_accounts.user_id');
    }

    private function applyProviderConcurrentLimit($query): void
    {
        if ($this->featureToggleRepository->enabled(FeatureToggle::PROVIDER_CONCURRENT_PAYING_TRANSACTION_LIMIT)) {
            $limitCount = $this->featureToggleRepository->valueOf(FeatureToggle::PROVIDER_CONCURRENT_PAYING_TRANSACTION_LIMIT);

            $query->leftJoinSub(
                $this->getPayingTransactionsSubquery(),
                'paying_transactions',
                'paying_transactions.from_id',
                '=',
                'user_channel_accounts.user_id'
            )->where(DB::raw('IFNULL(paying_transactions.total_count, 0)'), '<', $limitCount);
        }
    }

    private function getPayingTransactionsSubquery()
    {
        return Transaction::select(['from_id', DB::raw('COUNT(transactions.id) AS total_count')])
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->where('status', Transaction::STATUS_PAYING)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->groupBy('from_id');
    }

    private function applyBalanceLimits($query, Transaction $transaction): void
    {
        $query->where(function ($q) use ($transaction) {
            $q->where('user_channel_accounts.balance_limit', '>=', DB::raw("user_channel_accounts.balance + {$transaction->floating_amount}"))
                ->orWhere('user_channel_accounts.balance_limit', '0')
                ->orWhereNull('user_channel_accounts.balance_limit');
        });

        if ($this->featureToggleRepository->enabled(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)) {
            $dailyLimit = $this->featureToggleRepository->valueOf(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT);
            $query->where(function ($q) use ($dailyLimit, $transaction) {
                $q->orWhere('daily_status', '0')
                    ->orWhere(DB::raw("IFNULL(daily_limit, {$dailyLimit})"), '>=', DB::raw("daily_total + {$transaction->floating_amount}"));
            });
        }

        if ($this->featureToggleRepository->enabled(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT)) {
            $monthlyLimit = $this->featureToggleRepository->valueOf(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT);
            $query->where(function ($q) use ($monthlyLimit, $transaction) {
                $q->orWhere('monthly_status', '0')
                    ->orWhere(DB::raw("IFNULL(monthly_limit, {$monthlyLimit})"), '>=', DB::raw("monthly_total + {$transaction->floating_amount}"));
            });
        }
    }

    private function applySingleTransactionLimits($query, Transaction $transaction): void
    {
        $query->where(function ($q) use ($transaction) {
            $q->where('user_channel_accounts.single_min_limit', '<=', $transaction->floating_amount)
                ->orWhereNull('user_channel_accounts.single_min_limit');
        })->where(function ($q) use ($transaction) {
            $q->where('user_channel_accounts.single_max_limit', '>=', $transaction->floating_amount)
                ->orWhere('user_channel_accounts.single_max_limit', '0')
                ->orWhereNull('user_channel_accounts.single_max_limit');
        });
    }

    private function applyFloatingAmountRestriction($query, Channel $channel, Transaction $transaction): void
    {
        if ($channel->floating_enable) {
            $subquery = Transaction::select(['from_id', DB::raw('COUNT(transactions.id) AS total_count')])
                ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
                ->where('channel_code', $channel->code)
                ->where('status', Transaction::STATUS_PAYING)
                ->where('floating_amount', '=', $transaction->floating_amount)
                ->where('created_at', '>=', now()->subMinutes(30))
                ->groupBy('from_id');

            $query->leftJoinSub($subquery, 'paying_transactions', 'paying_transactions.from_id', '=', 'user_channel_accounts.user_id')
                ->where(DB::raw('IFNULL(paying_transactions.total_count, 0)'), '=', 0);
        }
    }

    private function applyPayingTransactionsRestriction($query, Transaction $transaction, Channel $channel): void
    {
        if (!request()->input('match_last_account') && !$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $featureToggle = FeatureToggle::ALLOW_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT;

            $query->whereDoesntHave('devicePayingTransactions.transaction', function ($q) use ($transaction, $channel, $featureToggle) {
                $q->where('channel_code', $transaction->channel_code);

                if (!$channel->max_one_ignore_amount) {
                    $q->where('amount', $transaction->floating_amount);
                }

                if ($this->featureToggleRepository->enabled($featureToggle)) {
                    $q->where('amount', $transaction->floating_amount)
                        ->whereRaw('JSON_CONTAINS(to_channel_account, ?)', json_encode(['real_name' => $transaction->to_channel_account['real_name'] ?? '']));
                }
            });
        }
    }

    private function applyUserAndAccountStatus($query): void
    {
        $query->where([
            ['users.transaction_enable', User::STATUS_ENABLE],
            ['users.status', User::STATUS_ENABLE],
            ['user_channel_accounts.status', UserChannelAccount::STATUS_ONLINE],
        ]);
    }

    private function applyAccountType($query): void
    {
        $query->where('user_channel_accounts.type', '!=', UserChannelAccount::TYPE_WITHDRAW);
    }

    private function applyAddressTypeFilter($query): void
    {
        // USDT 收款只匹配母地址，子地址由系統自動衍生
        $query->where(function ($q) {
            $q->where('user_channel_accounts.channel_code', '!=', 'USDT')
              ->orWhere(function ($q2) {
                  $q2->where('user_channel_accounts.channel_code', 'USDT')
                     ->where('user_channel_accounts.address_type', UserChannelAccount::ADDRESS_TYPE_MASTER);
              });
        });
    }

    private function applyChannelAmountAndFee($query, ChannelAmount $channelAmount, UserChannel $merchantUserChannel): void
    {
        $query->where([
            ['channel_amount_id', $channelAmount->getKey()],
            ['fee_percent', '<=', $merchantUserChannel->fee_percent],
        ]);
    }

    private function applyReadyForMatching($query): void
    {
        if (!$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $query->where('users.ready_for_matching', true);
        }
    }

    private function applyTimeLimit($query): void
    {
        if ($this->featureToggleRepository->enabled(FeatureToggle::LATE_NIGHT_BANK_LIMIT)) {
            $query->where('time_limit_disabled', false);
        }
    }

    private function applyWalletBalanceConditions($query, Transaction $transaction): void
    {
        if (!$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $query->whereHas('wallet', function (Builder $walletBuilder) use ($transaction) {
                $minimumRequiredBalance = $transaction->floating_amount;

                if ($this->featureToggleRepository->enabled(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE)) {
                    $minimumRequiredBalance = $this->bcMath->max(
                        $minimumRequiredBalance,
                        $this->featureToggleRepository->valueOf(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE, 0)
                    );
                }

                $walletBuilder->where(DB::raw('balance - frozen_balance'), '>=', $minimumRequiredBalance);

                if ($this->featureToggleRepository->enabled(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE_IN_PERCENT)) {
                    $value = $this->featureToggleRepository->valueOf(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE_IN_PERCENT, 0);
                    if ($value > 0) {
                        $percent = $value / 100;
                        $walletBuilder->where(DB::raw("(balance - frozen_balance) * $percent"), '>=', $transaction->floating_amount);
                    }
                }
            });
        }
    }

    private function applyBankRestrictions($query, Channel $channel): void
    {
        if (request()->filled('bank_name')) {
            $query->whereHas('bank', function (Builder $channelBanks) {
                $channelBanks->where('name', request()->input('bank_name'));
            });
        }
    }

    private function applyTransactionGroupConditions($query, Transaction $transaction): void
    {
        $currentMerchantInTransactionGroup = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->where('owner_id', $transaction->to_id)
            ->exists();

        $query->when($currentMerchantInTransactionGroup, function (Builder $userChannelAccounts) use ($transaction) {
            $userChannelAccounts->whereHas('transactionGroups', function (Builder $transactionGroups) use ($transaction) {
                $transactionGroups->where('owner_id', $transaction->to_id)
                    ->where('transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION);
            });
        })->when(!$currentMerchantInTransactionGroup, function (Builder $userChannelAccounts) {
            $userChannelAccounts->whereDoesntHave('transactionGroups');
        });
    }

    private function applyGeolocationMatching($query, Channel $channel): void
    {
        if ($channel->geolocation_match) {
            $ip = request()->input('client_ip', $this->whitelistedIpManager->extractIpFromRequest(request()));
            $city = optional(Location::get($ip))->cityName;
            $city = str_replace('\'', ' ', $city);
            $query->orderByRaw("users.last_login_city='{$city}' DESC");
        }
    }

    private function applyMatchingOrder($query): void
    {
        if (!$this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $query->orderBy('users.last_matched_at');
        }

        $matchType = $this->featureToggleRepository->valueOf(FeatureToggle::TRANSACTION_MATCH_TYPE);
        switch ($matchType) {
            case 0: // 輪詢匹配
                $query->orderBy('user_channel_accounts.last_matched_at');
                break;
            case 1: // 順序匹配
                break;
            case 2: // 隨機匹配
                $query->orderByRaw('RAND()');
                break;
        }
    }

    private function filterAccountsByAmountRestrictions(Collection $providerUserChannelAccounts, Transaction $transaction): Collection
    {
        return $providerUserChannelAccounts->filter(function ($userChannelAccount) use ($transaction) {
            $channelAmount = $userChannelAccount->channelAmount;
            $minAmount = (float) $userChannelAccount->min_amount > 0 ? $userChannelAccount->min_amount : $channelAmount->min_amount;
            $maxAmount = (float) $userChannelAccount->max_amount > 0 ? $userChannelAccount->max_amount : $channelAmount->max_amount;

            if ($minAmount && $maxAmount) {
                return $transaction->amount >= $minAmount && $transaction->amount <= $maxAmount;
            }

            if ($channelAmount->fixed_amount) {
                return in_array($transaction->amount, $channelAmount->fixed_amount);
            }

            return false;
        });
    }

    private function matchLastAccountIfRequested(Collection $filteredAccounts, Channel $channel): Collection
    {
        if (request()->input('match_last_account') && request()->has('real_name')) {
            $lastMatch = Transaction::where('channel_code', $channel->code)
                ->whereNotNull('from_channel_account_id')
                ->where('to_channel_account->real_name', request()->input('real_name'))
                ->orderByDesc('id')
                ->first();

            if ($lastMatch && $filteredAccounts->contains('id', $lastMatch->from_channel_account_id)) {
                return collect([$lastMatch->fromChannelAccount]);
            }
        }
        return $filteredAccounts;
    }

    private function replaceBankNames(Collection $matchedAccounts): Collection
    {
        return $matchedAccounts->map(function ($account) {
            $detail = $account->detail;
            $bankData = $account->bank;

            if (!empty($bankData) && isset($bankData->name) && data_get($detail, 'bank_name')) {
                data_set($detail, 'bank_name', $bankData->name);
                $account->detail = $detail;
            }

            return $account;
        });
    }
}
