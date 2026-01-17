<?php


namespace App\Http\Controllers\ThirdParty;


use App\Model\Channel;
use App\Model\ChannelAmount;
use App\Model\FeatureToggle;
use App\Model\Transaction;
use App\Model\TransactionGroup;
use App\Model\User;
use App\Model\UserChannel;
use App\Model\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Utils\WhitelistedIpManager;
use Stevebauman\Location\Facades\Location;

trait UserChannelAccountMatching
{
    private function findSuitableUserChannelAccounts(
        Transaction $transaction,
        Channel $channel,
        UserChannel $merchantUserChannel,
        ChannelAmount $channelAmount,
        FeatureToggleRepository $featureToggleRepository,
        BCMathUtil $bcMath
    ) {
        DB::enableQueryLog();

        $query = $this->initializeQuery();
        $lastCount = $query->count();

        $stages = [
            'Provider concurrent limit' => fn() => $this->applyProviderConcurrentLimit($query, $featureToggleRepository),
            'Balance limits' => fn() => $this->applyBalanceLimits($query, $transaction, $featureToggleRepository),
            'Single transaction limits' => fn() => $this->applySingleTransactionLimits($query, $transaction),
            'Floating amount restriction' => fn() => $this->applyFloatingAmountRestriction($query, $channel, $transaction),
            'Paying transactions restriction' => fn() => $this->applyPayingTransactionsRestriction($query, $transaction, $channel, $featureToggleRepository),
            'User and account status' => fn() => $this->applyUserAndAccountStatus($query),
            'Account type' => fn() => $this->applyAccountType($query),
            'Channel amount and fee' => fn() => $this->applyChannelAmountAndFee($query, $channelAmount, $merchantUserChannel),
            'Ready for matching' => fn() => $this->applyReadyForMatching($query, $featureToggleRepository),
            'Time limit' => fn() => $this->applyTimeLimit($query, $featureToggleRepository),
            'Wallet balance conditions' => fn() => $this->applyWalletBalanceConditions($query, $transaction, $featureToggleRepository, $bcMath),
            'Bank restrictions' => fn() => $this->applyBankRestrictions($query, $channel),
            'Transaction group conditions' => fn() => $this->applyTransactionGroupConditions($query, $transaction),
            'Geolocation matching' => fn() => $this->applyGeolocationMatching($query, $channel),
        ];

        $zeroResultStage = null;

        foreach ($stages as $stage => $applyCondition) {
            $applyCondition();
            $currentCount = $query->count();

            if ($currentCount == 0 && $lastCount > 0) {
                $zeroResultStage = $stage;
                $this->logQueryInfo($query, $stage);
                break;  // 早期退出
            }

            $lastCount = $currentCount;
        }

        // if ($zeroResultStage) {
        //     Log::warning("Query returned zero results at stage: {$zeroResultStage}");
        //     $this->analyzeQueryLog($zeroResultStage);
        //     return collect();
        // }

        $this->applyMatchingOrder($query, $featureToggleRepository);

        $providerUserChannelAccounts = $this->executeQuery($query);

        if ($providerUserChannelAccounts->isEmpty()) {
            return collect();
        }

        $filteredAccounts = $this->filterAccountsByAmountRestrictions($providerUserChannelAccounts, $transaction);
        $matchedAccounts = $this->matchLastAccountIfRequested($filteredAccounts, $channel);

        return $this->replaceBankNames($matchedAccounts);
    }

    private function logQueryInfo($query, $stageName)
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        Log::debug("Query {$stageName}: {$sql}", $bindings);

        $explainResults = DB::select('EXPLAIN ' . $sql, $bindings);
        Log::debug("Query {$stageName} execution plan:", $explainResults);
    }

    private function analyzeQueryLog($stageName)
    {
        $queryLog = DB::getQueryLog();
        $lastQuery = end($queryLog);  // 獲取最後一個查詢，假設這是當前階段的查詢

        if ($lastQuery) {
            $sql = $lastQuery['query'];
            $bindings = $lastQuery['bindings'];

            // 替換參數綁定
            foreach ($bindings as $binding) {
                $value = is_numeric($binding) ? $binding : "'" . addslashes($binding) . "'";
                $sql = preg_replace('/\?/', $value, $sql, 1);
            }

            Log::debug("Query {$stageName}:", [
                'sql' => $sql,
                'time' => $lastQuery['time']
            ]);
        }
    }
    private function initializeQuery()
    {
        return UserChannelAccount::query()
            ->with('bank', 'channelAmount')
            ->join('users', 'users.id', '=', 'user_channel_accounts.user_id');
    }

    private function getPayingTransactionsSubquery()
    {
        return Transaction::select(['from_id', DB::raw('COUNT(transactions.id) AS total_count')])
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->where('status', Transaction::STATUS_PAYING)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->groupBy('from_id');
    }

    private function applyBalanceLimits($query, Transaction $transaction, FeatureToggleRepository $featureToggleRepository)
    {
        $this->applyBalanceLimit($query, $transaction);
        $this->applyDailyLimit($query, $transaction, $featureToggleRepository);
        $this->applyMonthlyLimit($query, $transaction, $featureToggleRepository);
    }

    private function applyBalanceLimit($query, Transaction $transaction)
    {
        $query->where(function ($q) use ($transaction) {
            $q->where('user_channel_accounts.balance_limit', '>=', DB::raw("user_channel_accounts.balance + {$transaction->floating_amount}"))
                ->orWhere('user_channel_accounts.balance_limit', '0')
                ->orWhereNull('user_channel_accounts.balance_limit');
        });
    }

    private function applyDailyLimit($query, Transaction $transaction, FeatureToggleRepository $featureToggleRepository)
    {
        if ($featureToggleRepository->enabled(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT)) {
            $dailyLimit = $featureToggleRepository->valueOf(FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT);

            $query->where(function ($q) use ($dailyLimit, $transaction) {
                $q->orWhere('daily_status', '0')
                    ->orWhere(DB::raw("IFNULL(daily_limit, {$dailyLimit})"), '>=', DB::raw("daily_total + {$transaction->floating_amount}"));
            });
        }
    }

    private function applyMonthlyLimit($query, Transaction $transaction, FeatureToggleRepository $featureToggleRepository)
    {
        if ($featureToggleRepository->enabled(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT)) {
            $monthlyLimit = $featureToggleRepository->valueOf(FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT);

            $query->where(function ($q) use ($monthlyLimit, $transaction) {
                $q->orWhere('monthly_status', '0')
                    ->orWhere(DB::raw("IFNULL(monthly_limit, {$monthlyLimit})"), '>=', DB::raw("monthly_total + {$transaction->floating_amount}"));
            });
        }
    }

    private function applySingleTransactionLimits($query, Transaction $transaction)
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

    private function applyFloatingAmountRestriction($query, Channel $channel, Transaction $transaction)
    {
        if ($channel->floating_enable) {
            $query->leftJoinSub(
                $this->getFloatingAmountSubquery($channel, $transaction),
                'paying_transactions',
                'paying_transactions.from_id',
                '=',
                'user_channel_accounts.user_id'
            )->where(DB::raw('IFNULL(paying_transactions.total_count, 0)'), '=', 0);
        }
    }

    private function getFloatingAmountSubquery(Channel $channel, Transaction $transaction)
    {
        return Transaction::select(['from_id', DB::raw('COUNT(transactions.id) AS total_count')])
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->where('channel_code', $channel->code)
            ->where('status', Transaction::STATUS_PAYING)
            ->where('floating_amount', '=', $transaction->floating_amount)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->groupBy('from_id');
    }

    private function applyPayingTransactionsRestriction($query, Transaction $transaction, Channel $channel, FeatureToggleRepository $featureToggleRepository)
    {
        if (!request()->input('match_last_account') && !$featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            if (!in_array($transaction->channel_code, [Channel::CODE_QR_ALIPAY, Channel::CODE_ALIPAY_SAC, Channel::CODE_ALIPAY_BAC, Channel::CODE_ALIPAY_GC])) {
                $this->applyNormalPayingTransactionsRestriction($query, $transaction, $channel, $featureToggleRepository);
            } else {
                $this->applyAlipayPayingTransactionsRestriction($query, $transaction, $channel, $featureToggleRepository);
            }
        }
    }

    private function applyNormalPayingTransactionsRestriction($query, Transaction $transaction, Channel $channel, FeatureToggleRepository $featureToggleRepository)
    {
        $query->whereDoesntHave('devicePayingTransactions.transaction', function ($q) use ($transaction, $channel, $featureToggleRepository) {
            $q->where('channel_code', $transaction->channel_code);

            if (!$channel->max_one_ignore_amount) {
                $q->where('amount', $transaction->floating_amount);
            }

            if ($featureToggleRepository->enabled(FeatureToggle::ALLOW_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT)) {
                $q->where('amount', $transaction->floating_amount)
                    ->whereRaw('JSON_CONTAINS(to_channel_account, ?)', json_encode(['real_name' => $transaction->to_channel_account['real_name'] ?? '']));
            }
        });
    }

    private function applyAlipayPayingTransactionsRestriction($query, Transaction $transaction, Channel $channel, FeatureToggleRepository $featureToggleRepository)
    {
        $query->whereDoesntHave('devicePayingTransactions.transaction', function ($q) use ($transaction, $channel, $featureToggleRepository) {
            $q->where('channel_code', $transaction->channel_code);

            if (!$channel->max_one_ignore_amount) {
                $q->where('amount', $transaction->floating_amount);
            }
            if ($featureToggleRepository->enabled(FeatureToggle::ALLOW_QR_ALIPAY_USER_CHANNEL_CONCURRENT_FOR_SAME_AMOUNT)) {
                $q->where('amount', $transaction->floating_amount)
                    ->whereRaw('JSON_CONTAINS(to_channel_account, ?)', json_encode(['real_name' => $transaction->to_channel_account['real_name'] ?? '']));
            }
        });
    }

    private function applyGeneralConditions($query, ChannelAmount $channelAmount, UserChannel $merchantUserChannel, FeatureToggleRepository $featureToggleRepository)
    {
        $query->where([
            ['users.transaction_enable', User::STATUS_ENABLE],
            ['users.status', User::STATUS_ENABLE],
            ['user_channel_accounts.status', UserChannelAccount::STATUS_ONLINE],
            ['user_channel_accounts.type', '!=', UserChannelAccount::TYPE_WITHDRAW],
            ['channel_amount_id', $channelAmount->getKey()],
            ['fee_percent', '<=', $merchantUserChannel->fee_percent],
        ])
            ->when(!$featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM), function (Builder $builder) use ($query) {
                $beforeCount = $query->count();
                $builder->where('users.ready_for_matching', true);
                $afterCount = $query->count();

                $filteredOut = $beforeCount - $afterCount;
                if ($filteredOut > 0) {
                    Log::debug("Filtered out users with ready_for_matching = false", [
                        'before_count' => $beforeCount,
                        'after_count' => $afterCount,
                        'filtered_out' => $filteredOut
                    ]);
                }
            });

        if ($featureToggleRepository->enabled(FeatureToggle::LATE_NIGHT_BANK_LIMIT)) {
            $query->where('time_limit_disabled', false);
        }
    }

    private function applyWalletBalanceConditions($query, Transaction $transaction, FeatureToggleRepository $featureToggleRepository, BCMathUtil $bcMath)
    {
        if (!$featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $query->whereHas('wallet', function (Builder $walletBuilder) use ($transaction, $featureToggleRepository, $bcMath) {
                $minimumRequiredBalance = $transaction->floating_amount;

                if ($featureToggleRepository->enabled(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE)) {
                    $minimumRequiredBalance = $bcMath->max(
                        $minimumRequiredBalance,
                        $featureToggleRepository->valueOf(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE, 0)
                    );
                }

                $walletBuilder->where(DB::raw('balance - frozen_balance'), '>=', $minimumRequiredBalance);

                if ($featureToggleRepository->enabled(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE_IN_PERCENT)) {
                    $value = $featureToggleRepository->valueOf(FeatureToggle::FEATURE_MIN_PROVIDER_MATCHING_BALANCE_IN_PERCENT, 0);
                    if ($value > 0) {
                        $percent = $value / 100;
                        $walletBuilder->where(DB::raw("(balance - frozen_balance) * $percent"), '>=', $transaction->floating_amount);
                    }
                }
            });
        }
    }

    private function applyBankRestrictions($query, Channel $channel)
    {
        if (request()->filled('bank_name') && $channel->code != Channel::CODE_DC_BANK) {
            $query->whereHas('bank', function (Builder $channelBanks) {
                $channelBanks->where('name', request()->input('bank_name'));
            });
        }
    }

    private function applyTransactionGroupConditions($query, Transaction $transaction)
    {
        $currentMerchantInTransactionGroup = TransactionGroup::where('transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->where('owner_id', $transaction->to_id)
            ->exists();

        $query->when($currentMerchantInTransactionGroup, function (Builder $userChannelAccounts) use ($transaction) {
            $userChannelAccounts->whereHas('transactionGroups', function (Builder $transactionGroups) use ($transaction) {
                $transactionGroups->where('owner_id', $transaction->to_id)
                    ->where('transaction_type', Transaction::TYPE_PAUFEN_TRANSACTION);
            });
        })
            ->when(!$currentMerchantInTransactionGroup, function (Builder $userChannelAccounts) {
                $userChannelAccounts->whereDoesntHave('transactionGroups');
            });
    }

    private function applyGeolocationMatching($query, Channel $channel)
    {
        if ($channel->geolocation_match) {
            $whitelistedIpManager = new WhitelistedIpManager;
            $ip = request()->input('client_ip', $whitelistedIpManager->extractIpFromRequest(request()));
            $city = optional(Location::get($ip))->cityName;
            $city = str_replace('\'', ' ', $city);
            $query->orderByRaw("users.last_login_city='{$city}' DESC");
        }
    }

    private function applyMatchingOrder($query, FeatureToggleRepository $featureToggleRepository)
    {
        if (!$featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $query->orderBy('users.last_matched_at');
        }

        $matchType = $featureToggleRepository->valueOf(FeatureToggle::TRANSACTION_MATCH_TYPE);
        switch ($matchType) {
            case 0: // 輪詢匹配
                $query->orderBy('user_channel_accounts.last_matched_at');
                break;
            case 1: // 順序匹配
                // 不排序
                break;
            case 2: // 隨機匹配
                $query->orderByRaw('RAND()');
                break;
        }
    }

    private function executeQuery($query)
    {
        return $query->get(['user_channel_accounts.*']);
    }

    private function filterAccountsByAmountRestrictions($providerUserChannelAccounts, Transaction $transaction)
    {
        return $providerUserChannelAccounts->filter(function ($userChannelAccount) use ($transaction) {
            $channelAmount = $userChannelAccount->channelAmount;
            $minAmount = $userChannelAccount->min_amount ?? $channelAmount->min_amount;
            $maxAmount = $userChannelAccount->max_amount ?? $channelAmount->max_amount;

            if ($minAmount && $maxAmount) {
                return $transaction->amount >= $minAmount && $transaction->amount <= $maxAmount;
            }

            if ($channelAmount->fixed_amount) {
                return in_array($transaction->amount, $channelAmount->fixed_amount);
            }

            return false;
        });
    }

    private function matchLastAccountIfRequested($filteredAccounts, Channel $channel)
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

    private function replaceBankNames($matchedAccounts)
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

    private function applyProviderConcurrentLimit($query, $featureToggleRepository)
    {
        if ($featureToggleRepository->enabled(FeatureToggle::PROVIDER_CONCURRENT_PAYING_TRANSACTION_LIMIT)) {
            $limitCount = $featureToggleRepository->valueOf(FeatureToggle::PROVIDER_CONCURRENT_PAYING_TRANSACTION_LIMIT);

            $query->leftJoinSub(
                $this->getPayingTransactionsSubquery(),
                'paying_transactions',
                'paying_transactions.from_id',
                '=',
                'user_channel_accounts.user_id'
            )->where(DB::raw('IFNULL(paying_transactions.total_count, 0)'), '<', $limitCount);
        }
    }

    private function applyUserAndAccountStatus($query)
    {
        $query->where([
            ['users.transaction_enable', User::STATUS_ENABLE],
            ['users.status', User::STATUS_ENABLE],
            ['user_channel_accounts.status', UserChannelAccount::STATUS_ONLINE],
        ]);
    }

    private function applyAccountType($query)
    {
        $query->where('user_channel_accounts.type', '!=', UserChannelAccount::TYPE_WITHDRAW);
    }

    private function applyChannelAmountAndFee($query, $channelAmount, $merchantUserChannel)
    {
        $query->where([
            ['channel_amount_id', $channelAmount->getKey()],
            ['fee_percent', '<=', $merchantUserChannel->fee_percent],
        ]);
    }

    private function applyReadyForMatching($query, $featureToggleRepository)
    {
        if (!$featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM)) {
            $beforeCount = $query->count();
            $query->where('users.ready_for_matching', true);
            $afterCount = $query->count();

            $filteredOut = $beforeCount - $afterCount;
            if ($filteredOut > 0) {
                Log::debug("Query Ready for matching: Filtered out users with ready_for_matching = false", [
                    'before_count' => $beforeCount,
                    'after_count' => $afterCount,
                    'filtered_out' => $filteredOut
                ]);
            }
        }
    }

    private function applyTimeLimit($query, $featureToggleRepository)
    {
        if ($featureToggleRepository->enabled(FeatureToggle::LATE_NIGHT_BANK_LIMIT)) {
            $query->where('time_limit_disabled', false);
        }
    }
}
