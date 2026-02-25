<?php

namespace App\Services\Transaction;

use App\Exceptions\DifferentChildWithdrawStatusException;
use App\Exceptions\PaufenTransactionHasBeenLockedException;
use App\Exceptions\SeparatedWithdrawShouldCompleteChildrenException;
use App\Exceptions\TransactionLockerNotYouException;
use App\Exceptions\TransactionShouldLockBeforeUpdateException;
use App\Models\Transaction;
use App\Models\User;
use RuntimeException;

class TransactionStateValidator
{
    public function validatePaufenLock(Transaction $transaction, ?User $operator = null): void
    {
        // 交易鎖定後只有鎖定人操作，沒鎖定的話管理員及對應碼商可以操作
        throw_if(
            $transaction->isPaufenTransaction() && $transaction->locked && (!$operator || !$transaction->lockedBy->is($operator)),
            new PaufenTransactionHasBeenLockedException()
        );
    }

    public function validateLockBeforeUpdate(Transaction $transaction, ?User $operator = null): void
    {
        switch ($transaction->type) {
            case Transaction::TYPE_PAUFEN_TRANSACTION:
                // 只有操作人是管理員時才需要鎖定
                if ($operator && $operator->mainUser()->isAdmin()) {
                    throw_if(!$transaction->locked, new TransactionShouldLockBeforeUpdateException());

                    throw_if(
                        !$operator || !$transaction->lockedBy->is($operator),
                        new TransactionLockerNotYouException()
                    );
                }
                break;
            case Transaction::TYPE_PAUFEN_WITHDRAW:
            case Transaction::TYPE_NORMAL_DEPOSIT:
            case Transaction::TYPE_NORMAL_WITHDRAW:
            case Transaction::TYPE_INTERNAL_TRANSFER:
                // 提現、充值需先鎖定
                throw_if(!$transaction->locked, new TransactionShouldLockBeforeUpdateException());

                // 提現、充值鎖定人不符
                throw_if(
                    !$operator || !$transaction->lockedBy->is($operator),
                    new TransactionLockerNotYouException()
                );
                break;
            default:
                throw new RuntimeException();
        }
    }

    public function validateNotSeparatedParent(Transaction $transaction): void
    {
        throw_if(
            $transaction->isWithdraw() && $transaction->children()->exists(),
            new SeparatedWithdrawShouldCompleteChildrenException()
        );
    }

    public function validateChildCanBeSuccess(Transaction $transaction): void
    {
        throw_if(
            $transaction->isWithdraw()
                && $transaction->isChild()
                && Transaction::where('parent_id', $transaction->parent_id)->whereNotIn('status', [
                    Transaction::STATUS_PAYING,
                    Transaction::STATUS_MATCHING,
                    Transaction::STATUS_RECEIVED,
                    Transaction::STATUS_SUCCESS,
                    Transaction::STATUS_MANUAL_SUCCESS,
                    Transaction::STATUS_THIRD_PAYING
                ])->exists(),
            new DifferentChildWithdrawStatusException()
        );
    }

    public function validateChildCanBeFailed(Transaction $transaction): void
    {
        throw_if(
            $transaction->isWithdrawSeparatedChild()
                && Transaction::where('parent_id', $transaction->parent_id)->whereNotIn('status', [
                    Transaction::STATUS_MATCHING,
                    Transaction::STATUS_PAYING,
                    Transaction::STATUS_RECEIVED,
                    Transaction::STATUS_FAILED,
                    Transaction::STATUS_THIRD_PAYING
                ])->exists(),
            new DifferentChildWithdrawStatusException()
        );
    }
}
