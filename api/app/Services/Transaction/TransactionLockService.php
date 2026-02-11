<?php

namespace App\Services\Transaction;

use App\Models\Transaction;
use App\Models\User;
use App\Utils\TransactionLockFailed;
use App\Utils\TransactionUnlockFailed;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TransactionLockService
{
    public function supportLockingLogics(Transaction $transaction, Request $request, ?Closure $beforeLock = null, $force = false)
    {
        // lock
        if ($request->has('locked') && $request->boolean('locked')) {
            try {
                DB::transaction(function () use ($transaction, $request, $beforeLock, $force) {
                    if (is_callable($beforeLock)) {
                        $beforeLock();
                    }

                    $this->lock($transaction, $request->user()->realUser(), $force);
                });
            } catch (TransactionLockFailed $transactionLockFailed) {
                abort(Response::HTTP_BAD_REQUEST, $transactionLockFailed->getMessage());
            }
        }

        // unlock
        if ($request->has('locked') && !$request->boolean('locked')) {
            try {
                $this->unlock($transaction, $request->user()->realUser(), $force);
            } catch (TransactionUnlockFailed $transactionUnlockFailed) {
                abort(Response::HTTP_BAD_REQUEST, $transactionUnlockFailed->getMessage());
            }
        }
    }

    public function lock(Transaction $transaction, User $user, $force = false)
    {
        $isAdmin = $user->mainUser()->role === User::ROLE_ADMIN;
        throw_if(
            !$isAdmin && !$force,
            new InvalidArgumentException('Non admin user')
        );

        return DB::transaction(function () use ($transaction, $user) {
            $updatedRow = Transaction::whereNull('locked_at')
                ->whereNull('locked_by_id')
                ->where('id', $transaction->getKey())
                ->update([
                    'locked_at'    => now(),
                    'locked_by_id' => $user->getKey(),
                ]);

            throw_if($updatedRow > 1, new RuntimeException());

            if ($updatedRow !== 1) {
                $transaction->refresh();

                throw_if(
                    $transaction->locked,
                    new TransactionLockFailed(__('transaction.Locked failed, already been locked'))
                );

                throw new RuntimeException();
            }

            return $transaction->refresh();
        });
    }

    public function unlock(Transaction $transaction, User $user, $force = false)
    {
        $isAdmin = $user->mainUser()->role === User::ROLE_ADMIN;
        throw_if(
            !$isAdmin && !$force,
            new InvalidArgumentException('Non admin user')
        );

        return DB::transaction(function () use ($transaction, $user) {
            if ($user->god) {
                $updatedRow = Transaction::whereNotNull('locked_at')
                    ->where('id', $transaction->getKey())
                    ->update([
                        'locked_at'    => null,
                        'locked_by_id' => null,
                    ]);
            } else {
                $updatedRow = Transaction::whereNotNull('locked_at')
                    ->where(function ($builder) use ($user) {
                        $builder->where('locked_by_id', $user->getKey())
                            ->orWhereHas('lockedBy', function ($builder) use ($user) {
                                $builder->where('parent_id', $user->getKey());
                            });
                    })
                    ->where('id', $transaction->getKey())
                    ->update([
                        'locked_at'    => null,
                        'locked_by_id' => null,
                    ]);
            }

            throw_if($updatedRow > 1, new RuntimeException());

            if ($updatedRow !== 1) {
                $transaction->refresh();

                throw_if(
                    !$transaction->locked,
                    new TransactionUnlockFailed(__('Unlocked failed, already been unlocked'))
                );

                throw_if(
                    $transaction->locked
                        && !$transaction->locked_by_id == auth()->id()
                        && !auth()->user()->isAdmin(),
                    new TransactionUnlockFailed(__('transaction.Unlocked failed, you are not lock owner'))
                );

                throw new RuntimeException();
            }

            return $transaction->refresh();
        });
    }
}
