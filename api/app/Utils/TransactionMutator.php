<?php

namespace App\Utils;

use App\Exceptions\RaceConditionException;
use App\Jobs\ProcessUsdtWithdraw;
use App\Models\Channel;
use App\Models\DevicePayingTransaction;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionFeeService;
use App\Services\Transaction\TransactionStatusRules;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionMutator
{
    public function __construct(
        private BCMathUtil $bcMath,
        private UserChannelAccountUtil $userChannelAccountUtil,
        private FeatureToggleRepository $featureToggleRepository,
        private TransactionFeeService $transactionFeeService
    ) {}

    public function paufenDepositTo(
        User $provider,
        Transaction $transaction,
        ?Transaction $parent = null
    ) {
        return DB::transaction(function () use (
            $provider,
            $transaction,
            $parent
        ) {
            $success =
                Transaction::where("id", $transaction->getKey())
                ->whereNull("to_id")
                ->whereNull("locked_at")
                ->where("status", Transaction::STATUS_MATCHING)
                ->where("type", Transaction::TYPE_PAUFEN_WITHDRAW)
                ->update([
                    "to_id" => $provider->getKey(),
                    "to_wallet_id" => $provider->wallet->getKey(),
                    "to_account_mode" => $provider->account_mode,
                    "status" => Transaction::STATUS_PAYING,
                    "matched_at" => now(),
                ]) === 1;

            throw_if(
                !$success &&
                    $transaction->refresh()->status !==
                    Transaction::STATUS_MATCHING,
                new RaceConditionException()
            );

            throw_if(!$success, new RuntimeException("Unknown"));

            $this->transactionFeeService->createDepositFees($transaction, $provider, false, $parent);

            return $transaction;
        });
    }

    public function paufenDepositToAccount(
        UserChannelAccount $account,
        Transaction $transaction,
        ?Transaction $parent = null
    ) {
        $result = DB::transaction(function () use (
            $account,
            $transaction,
            $parent
        ) {
            $featureToggleRepository = app(FeatureToggleRepository::class);

            throw_if(
                $featureToggleRepository->enabled(
                    FeatureToggle::RECORD_USER_CHANNEL_ACCOUNT_BALANCE,
                    false
                ) && $account->balance < $transaction->floating_amount,
                new InsufficientAvailableBalance()
            );

            $provider = $account->user;
            $fromChannelAccount = $transaction->from_channel_account;

            $success =
                Transaction::where("id", $transaction->getKey())
                ->whereNull("to_id")
                ->whereNull("locked_at")
                ->where("status", Transaction::STATUS_MATCHING)
                ->where("type", Transaction::TYPE_PAUFEN_WITHDRAW)
                ->update([
                    "to_id" => $provider->getKey(),
                    "to_wallet_id" => $provider->wallet->getKey(),
                    "to_channel_account_id" => $account->id,
                    "to_channel_account" => array_merge($account->detail, [
                        "channel_code" => $account->channel_code,
                    ]),
                    "from_channel_account" => $fromChannelAccount,
                    "to_account_mode" => $provider->account_mode,
                    "status" => Transaction::STATUS_PAYING,
                    "matched_at" => now(),
                ]) === 1;

            throw_if(
                !$success &&
                    $transaction->refresh()->status !==
                    Transaction::STATUS_MATCHING,
                new RaceConditionException()
            );

            throw_if(!$success, new RuntimeException("Unknown"));

            $this->transactionFeeService->createDepositFees($transaction, $provider, false, $parent);

            $this->userChannelAccountUtil->updateTotal(
                $account->id,
                $transaction->floating_amount,
                true
            );

            return $transaction;
        });

        // USDT 自營出款：DB transaction 完成後自動發送鏈上交易
        $result->refresh();
        if (TransactionStatusRules::shouldDispatchUsdtWithdraw(
            false, $result->channel_code, $result->thirdchannel_id, $result->from_channel_account_id
        )) {
            ProcessUsdtWithdraw::dispatch($result->id);
        }

        return $result;
    }

    public function paufenTransactionFrom(
        UserChannelAccount $providerUserChannelAccount,
        Transaction $transaction
    ) {
        $transaction = DB::transaction(function () use (
            $providerUserChannelAccount,
            $transaction
        ) {
            $bcMath = app(BCMathUtil::class);

            $defaultDailyLimit = 0;
            $dailyLimitEnabled = $this->featureToggleRepository->enabled(
                FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT
            );
            if ($dailyLimitEnabled) {
                $defaultDailyLimit = $this->featureToggleRepository->valueOf(
                    FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT
                );
            }

            $defaultMonthlyLimit = 0;
            $monthlyLimitEnabled = $this->featureToggleRepository->enabled(
                FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT
            );
            if ($monthlyLimitEnabled) {
                $defaultMonthlyLimit = $this->featureToggleRepository->valueOf(
                    FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT
                );
            }

            $account = UserChannelAccount::find(
                $providerUserChannelAccount->id
            );
            $amount = Transaction::where(
                "from_channel_account_id",
                $account->id
            )
                ->where("status", Transaction::STATUS_PAYING)
                ->where("created_at", ">=", now()->subMinutes(15))
                ->sum("amount");

            if (
                $account->balance_limit != 0 &&
                $bcMath->sum([
                    $account->balance,
                    $amount,
                    $transaction->amount,
                ]) > $account->balance_limit
            ) {
                throw new RuntimeException("{$account->account}餘度不足");
            }

            $dailyLimit =
                $dailyLimitEnabled && $account->daily_status
                ? $account->daily_limit
                : $defaultDailyLimit;
            if (
                $dailyLimit &&
                $bcMath->sum([
                    $account->daily_total,
                    $amount,
                    $transaction->amount,
                ]) > $dailyLimit
            ) {
                throw new RuntimeException("{$account->account}日收餘度不足");
            }

            $monthlyLimit =
                $monthlyLimitEnabled && $account->monthly_status
                ? $account->monthly_limit
                : $defaultMonthlyLimit;
            if (
                $monthlyLimit &&
                $bcMath->sum([
                    $account->monthly_total,
                    $amount,
                    $transaction->amount,
                ]) > $monthlyLimit
            ) {
                throw new RuntimeException("{$account->account}月收餘度不足");
            }

            $updatedRow = Transaction::where([
                ["id", $transaction->getKey()],
                ["status", Transaction::STATUS_MATCHING],
            ])->update([
                "from_id" => $providerUserChannelAccount->user_id,
                "from_wallet_id" => $providerUserChannelAccount->wallet_id,
                "from_channel_account_id" => $providerUserChannelAccount->getKey(),
                "from_account_mode" =>
                $providerUserChannelAccount->user->account_mode,
                "from_channel_account" => array_merge(
                    $providerUserChannelAccount->detail,
                    ["account" => $providerUserChannelAccount->account]
                ),
                "status" => Transaction::STATUS_PAYING,
                "matched_at" => now(),
                "from_channel_account_hash_id" =>
                $providerUserChannelAccount->name,
                "from_device_name" => optional(
                    $providerUserChannelAccount->device
                )->name,
            ]);

            if ($updatedRow !== 1) {
                throw new RuntimeException("Conflict");
            }

            return $transaction->refresh();
        });

        if (
            !$this->featureToggleRepository->enabled(
                FeatureToggle::CANCEL_PAUFEN_MECHANISM
            )
        ) {
            DevicePayingTransaction::create([
                "device_id" => $providerUserChannelAccount->device_id,
                "user_channel_account_id" => $providerUserChannelAccount->getKey(),
                "amount" => $transaction->floating_amount,
                "transaction_id" => $transaction->getKey(),
                "created_at" => now(),
                "updated_at" => now(),
            ]);
        }

        $this->transactionFeeService->createPaufenTransactionFees(
            $transaction,
            $providerUserChannelAccount->channelAmount->channelGroup
        );

        return $transaction;
    }
}
