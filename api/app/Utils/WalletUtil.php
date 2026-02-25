<?php


namespace App\Utils;

use App\Exceptions\RaceConditionException;
use App\Models\MatchingDepositReward;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHistory;
use Illuminate\Support\Facades\DB;

class WalletUtil
{

    /**
     * @var BCMathUtil
     */
    private $bcMath;

    private WalletBalanceCalculator $calculator;

    private WalletHistoryRecorder $recorder;

    public function __construct(BCMathUtil $bcMath, WalletBalanceCalculator $calculator, WalletHistoryRecorder $recorder)
    {
        $this->bcMath = $bcMath;
        $this->calculator = $calculator;
        $this->recorder = $recorder;
    }

    private function walletSnapshot(Wallet $wallet): array
    {
        return [
            'balance'        => $wallet->balance,
            'profit'         => $wallet->profit,
            'frozen_balance' => $wallet->frozen_balance,
        ];
    }

    public function conflictAwaredBalanceUpdate(Wallet $wallet, array $delta, $note = null, $type = WalletHistory::TYPE_SYSTEM_ADJUSTING)
    {
        $this->calculator->validateConflictAwareUpdate($this->walletSnapshot($wallet), $delta);

        $resultAttributes = $this->calculator->computeResult($this->walletSnapshot($wallet), $delta);

        $updated = $wallet->update($resultAttributes);

        if (!$updated) {
            throw new RaceConditionException();
        }

        $walletHistory = WalletHistory::latest()->first();
        $adjustmentNumber = ($walletHistory->id ?? 0) + 1;

        $this->recorder->recordWithOperator(
            $wallet,
            auth()->user() ? auth()->user()->realUser()->getKey() : 0,
            $type,
            $delta,
            $resultAttributes,
            config('wallethistory.system_adjustment_number_prefix') . date('YmdHis') . $adjustmentNumber . " " . $note
        );

        return $wallet->refresh();
    }


    public function deposit(Wallet $wallet, string $amount, $profit, $note = '', bool $deduct_frozen_balance = false)
    {
        if ($amount == 0 && $profit == 0) return true;

        return DB::transaction(function () use ($wallet, $amount, $profit, $note, $deduct_frozen_balance) {
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->getKey());

            if ($deduct_frozen_balance) {
                $delta = $this->calculator->computeDepositDeductFrozenDelta($amount, $wallet->frozen_balance);

                $this->conflictAwaredBalanceUpdate($wallet, $delta, $note, WalletHistory::TYPE_DEPOSIT_DEDUCT_FROZEN_BALANCE);
            } else {
                $delta = $this->calculator->computeDepositDelta($amount, $profit);
                $result = $this->calculator->computeResult($this->walletSnapshot($wallet), $delta);

                $updatedRows = $this->updateWallet($wallet, $delta);

                $type = $this->calculator->determineDepositHistoryType($delta['balance']);

                $this->recorder->recordSystem($wallet, $type, $delta, $result, $note);

                return $updatedRows;
            }
        });
    }

    public function depositRollback(Wallet $wallet, string $amount, $profit, $note = '', $frozen_balance = 0)
    {
        if ($amount == 0 && $profit == 0) return true;

        return DB::transaction(function () use ($wallet, $amount, $profit, $note, $frozen_balance) {
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->getKey());

            $delta = $this->calculator->computeDepositRollbackDelta($amount, $profit, $frozen_balance);
            $this->calculator->validateDepositRollback($wallet->available_balance, $wallet->profit, $delta);

            $result = $this->calculator->computeResult($this->walletSnapshot($wallet), $delta);

            $updatedRows = $this->updateWallet($wallet, $delta);

            $this->recorder->recordSystem($wallet, WalletHistory::TYPE_DEPOSIT_ROLLBACK, $delta, $result, $note);

            return $updatedRows;
        });
    }

    /**
     * @param  Wallet  $wallet
     * @param  array  $delta
     * @return int
     */
    private function updateWallet(Wallet $wallet, array $delta)
    {
        $delta['frozen_balance'] = 0; // 不能用這個方法修改凍結餘額

        if (isset($delta['balance'])) {
            $this->calculator->validateAvailableBalance($wallet->available_balance, $delta['balance']);
        }

        if (isset($delta['profit'])) {
            $this->calculator->validateProfit($wallet->profit, $delta['profit']);
        }

        $resultAttributes = [
            'balance'        => $this->bcMath->add($wallet->balance, $delta['balance']),
            'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
            'frozen_balance' => $wallet->frozen_balance,
        ];

        $updated = $wallet->update($resultAttributes);

        if (!$updated) {
            throw new RaceConditionException();
        }

        return $wallet->refresh();
    }

    public function matchingDepositReward(Transaction $transaction, MatchingDepositReward $matchingDepositReward)
    {
        $reward = $this->calculator->computeRewardAmount(
            $transaction->amount,
            $matchingDepositReward->reward_amount,
            $matchingDepositReward->reward_unit
        );

        if ($reward == 0) return true;

        return DB::transaction(function () use ($transaction, $reward, $matchingDepositReward) {
            $user = $transaction->to()->first();
            $isMerchant = $user->role === User::ROLE_MERCHANT;
            $wallet = Wallet::lockForUpdate()->findOrFail($transaction->to_wallet_id);
            $orderNumber = $isMerchant ? $transaction->order_number : $transaction->system_order_number;

            $delta = $this->calculator->computeRewardDelta($reward);
            $result = $this->calculator->computeResult($this->walletSnapshot($wallet), $delta);

            $updatedRows = $this->updateWallet($wallet, $delta);

            $note = $this->calculator->formatMatchingDepositRewardNote(
                $matchingDepositReward->min_amount,
                $matchingDepositReward->max_amount,
                $matchingDepositReward->reward_amount,
                $matchingDepositReward->reward_unit
            );

            $this->recorder->recordSystem($wallet, WalletHistory::TYPE_MATCHING_DEPOSIT_REWARD, $delta, $result, $orderNumber . ' ' . $note);

            return $updatedRows;
        });
    }

    /**
     * @param  Wallet  $from
     * @param  Wallet  $to
     * @param  string  $amount
     * @param  User  $operator
     * @param  string  $note
     * @return mixed
     */
    public function transfer(
        Wallet $from,
        Wallet $to,
        string $amount,
        User $operator,
        string $note
    ) {
        return DB::transaction(function () use ($from, $to, $amount, $operator, $note) {
            $from = Wallet::lockForUpdate()->findOrFail($from->getKey());
            $to = Wallet::lockForUpdate()->findOrFail($to->getKey());

            $fromDelta = $this->calculator->computeTransferFromDelta($amount);
            $fromResult = $this->calculator->computeResult($this->walletSnapshot($from), $fromDelta);

            $fromWalletUpdatedRow = $this->updateWallet($from, $fromDelta);

            $this->recorder->recordWithOperator(
                $from,
                $operator->realUser()->getKey(),
                WalletHistory::TYPE_TRANSFER,
                $fromDelta,
                $fromResult,
                $note . " 转出给 {$to->user->name} {$to->user->username}"
            );

            $toDelta = $this->calculator->computeTransferToDelta($amount);
            $toResult = $this->calculator->computeResult($this->walletSnapshot($to), $toDelta);

            $toWalletUpdatedRow = $this->updateWallet($to, $toDelta);

            $this->recorder->recordWithOperator(
                $to,
                $operator->realUser()->getKey(),
                WalletHistory::TYPE_TRANSFER,
                $toDelta,
                $toResult,
                $note . " 由 {$from->user->name} {$from->user->username} 转入"
            );

            return $fromWalletUpdatedRow + $toWalletUpdatedRow;
        });
    }

    public function withdraw(Wallet $wallet, string $amount, $note, string $transactionType, $withdrawType = 'balance')
    {
        if ($amount == 0)  return true;

        return DB::transaction(function () use ($wallet, $amount, $note, $transactionType, $withdrawType) {
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->getKey());

            $delta = $this->calculator->computeWithdrawDelta($amount, $withdrawType);
            $result = $this->calculator->computeResult($this->walletSnapshot($wallet), $delta);

            $updatedRows = $this->updateWallet($wallet, $delta);

            $this->recorder->recordSystem($wallet, $this->calculator->determineWithdrawHistoryType($transactionType), $delta, $result, $note);

            return $updatedRows;
        });
    }

    public function withdrawRollback(Wallet $wallet, string $amount, $note, string $transactionType, $withdrawType = 'balance')
    {
        if ($amount == 0)  return true;

        return DB::transaction(function () use ($wallet, $amount, $note, $transactionType, $withdrawType) {
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->getKey());

            $delta = $this->calculator->computeWithdrawRollbackDelta($amount, $withdrawType);
            $result = $this->calculator->computeResult($this->walletSnapshot($wallet), $delta);

            $updatedRows = $this->updateWallet($wallet, $delta);

            $this->recorder->recordSystem($wallet, $this->calculator->determineWithdrawRollbackHistoryType($transactionType), $delta, $result, $note);

            return $updatedRows;
        });
    }
}
