<?php

namespace App\Services\Transaction;

use App\Exceptions\InvalidStatusException;
use App\Exceptions\RaceConditionException;
use App\Exceptions\TransactionRefundedException;
use App\Jobs\NotifyTransaction;
use App\Jobs\ProcessUsdtWithdraw;
use App\Models\Channel;
use App\Models\DevicePayingTransaction;
use App\Models\FeatureToggle;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\TransactionNote;
use App\Models\User;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\InsufficientAvailableBalance;
use App\Utils\UserChannelAccountUtil;
use App\Utils\WalletUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransactionStatusService
{
    private $wallet;
    private $bcMath;
    private $featureToggleRepository;
    private $transactionFeeService;
    private $userChannelAccountUtil;
    private $stateValidator;
    private $settlementService;
    private $separationService;
    private $cancelPaufen;

    public function __construct(
        WalletUtil $wallet,
        BCMathUtil $bcMath,
        FeatureToggleRepository $featureToggleRepository,
        TransactionFeeService $transactionFeeService,
        UserChannelAccountUtil $userChannelAccountUtil,
        TransactionStateValidator $stateValidator,
        TransactionSettlementService $settlementService,
        TransactionSeparationService $separationService
    ) {
        $this->wallet = $wallet;
        $this->bcMath = $bcMath;
        $this->featureToggleRepository = $featureToggleRepository;
        $this->transactionFeeService = $transactionFeeService;
        $this->userChannelAccountUtil = $userChannelAccountUtil;
        $this->stateValidator = $stateValidator;
        $this->settlementService = $settlementService;
        $this->separationService = $separationService;
        $this->cancelPaufen = $this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM);
    }

    public function markAsPaufenWithdraw(Transaction $transaction, ?User $provider, bool $shouldLock = true)
    {
        abort_if(
            !empty($provider) && ($provider->role !== User::ROLE_PROVIDER),
            Response::HTTP_BAD_REQUEST,
            '请指定码商'
        );

        abort_if(
            !in_array($transaction->type, [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW]),
            Response::HTTP_BAD_REQUEST,
            '订单类型不正确'
        );

        abort_if(
            $shouldLock
                && !$transaction->locked,
            Response::HTTP_BAD_REQUEST,
            '请先锁定'
        );

        abort_if(
            $shouldLock
                && !$transaction->locked_by_id == auth()->id()
                && !auth()->user()->isAdmin(),
            Response::HTTP_BAD_REQUEST,
            '非锁定人无法操作'
        );

        abort_if(
            !in_array(
                $transaction->status,
                [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]
            ),
            Response::HTTP_BAD_REQUEST,
            '目前状态无法转为码商出'
        );

        return DB::transaction(function () use ($transaction, $provider, $shouldLock) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->getKey());

            // 扣除出款帳號的日/月限額
            if ($transaction->to_channel_account_id) {
                $this->userChannelAccountUtil->updateTotalRollback($transaction->to_channel_account_id, $transaction->floating_amount, true);
            }

            $transaction->update([
                'locked_at'          => null,
                'locked_by_id'       => null,
                'to_id'              => optional($provider)->getKey(),
                'to_wallet_id'       => optional(optional($provider)->wallet)->getKey(),
                'to_channel_account_id' => null, // 指定碼商出時出款帳號為 null
                'type'               => Transaction::TYPE_PAUFEN_WITHDRAW,
                'status'             => !empty($provider) ? Transaction::STATUS_PAYING : Transaction::STATUS_MATCHING,
                'to_account_mode'    => optional($provider)->account_mode,
                'to_channel_account' => [],
                'matched_at'         => !empty($provider) ? now() : null,
                'note'               => null,
            ]);

            $transaction->transactionFees()->delete();

            $this->transactionFeeService->createWithdrawFees($transaction, $transaction->from, $transaction->sub_type == Transaction::SUB_TYPE_AGENCY_WITHDRAW);

            if (!empty($provider)) {
                $this->transactionFeeService->createDepositFees($transaction, $provider, false);
            }

            return $transaction;
        });
    }

    public function markAsThirdChannelWithdraw(Transaction $transaction, ?ThirdChannel $thirdChannel, bool $shouldLock = true)
    {
        return DB::transaction(function () use ($transaction, $thirdChannel, $shouldLock) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->getKey());

            abort_if(
                !in_array($transaction->type, [Transaction::TYPE_NORMAL_WITHDRAW]) || !$transaction->order_number,
                Response::HTTP_BAD_REQUEST,
                '订单类型不正确'
            );

            abort_if(
                $shouldLock
                    && !$transaction->locked,
                Response::HTTP_BAD_REQUEST,
                '请先锁定'
            );

            abort_if(
                $shouldLock
                    && !$transaction->locked_by_id == auth()->id()
                    && !auth()->user()->isAdmin(),
                Response::HTTP_BAD_REQUEST,
                '非锁定人无法操作'
            );

            abort_if(
                !in_array(
                    $transaction->status,
                    [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING]
                ),
                Response::HTTP_BAD_REQUEST,
                '目前状态无法转为三方出'
            );

            $transaction->update([
                'to_id'              => null,
                'to_wallet_id'       => null,
                'status'             => Transaction::STATUS_THIRD_PAYING,
                'matched_at'         => !empty($thirdChannel) ? now() : null,
                'thirdchannel_id'    => $thirdChannel->id
            ]);

            // 變成三方代付後，要刪除原本手續費
            $transaction->transactionFees()->delete();

            $this->transactionFeeService->createWithdrawFees($transaction, $transaction->from, $transaction->sub_type == Transaction::SUB_TYPE_AGENCY_WITHDRAW);

            return $transaction->refresh();
        });
    }

    /**
     * @param  Transaction  $transaction
     * @param  User|null  $operator
     * @return Transaction
     */
    public function markAsReceived(Transaction $transaction, ?User $operator = null)
    {
        return DB::transaction(function () use ($transaction, $operator) {
            $updatedRow = Transaction::whereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_THIRD_PAYING])
                ->whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])
                ->where('id', $transaction->getKey())
                ->update([
                    'operator_id' => optional($operator)->getKey(),
                    'status'      => Transaction::STATUS_RECEIVED,
                    'operated_at' => now(),
                ]);

            throw_if($updatedRow === 0, new RaceConditionException());

            throw_if($updatedRow !== 1, new RuntimeException('Update conflict'));

            return $transaction->refresh();
        });
    }

    public function markAsSuccess(
        Transaction $transaction,
        ?User $operator = null,
        $autoSuccess = false,
        $fromPayingTimedOut = false,
        $shouldLock = true
    ) {
        // 補單的行為會重複執行這個 Function，所以要讓這個 Function 重複執行時忽略手續費等等計算
        if ($transaction->isSuccessful()) {
            return $transaction;
        }

        $statusFilter = ($fromPayingTimedOut ? [Transaction::STATUS_PAYING_TIMED_OUT] : [Transaction::STATUS_MATCHING, Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED, Transaction::STATUS_THIRD_PAYING]);

        throw_if(!in_array($transaction->status, $statusFilter), new InvalidStatusException());

        $this->stateValidator->validatePaufenLock($transaction, $operator);
        if ($shouldLock) {
            $this->stateValidator->validateLockBeforeUpdate($transaction, $operator);
        }

        $this->stateValidator->validateNotSeparatedParent($transaction);
        $this->stateValidator->validateChildCanBeSuccess($transaction);

        $transaction = DB::transaction(function () use ($transaction, $autoSuccess, $fromPayingTimedOut, $operator, $shouldLock) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->getKey());

            $transaction->update([
                'operator_id'   => optional($operator)->getKey(),
                'status'        => $autoSuccess ? Transaction::STATUS_SUCCESS : Transaction::STATUS_MANUAL_SUCCESS,
                'actual_amount' => $transaction->floating_amount,
                'notify_status' => $transaction->notify_url ? Transaction::NOTIFY_STATUS_PENDING : Transaction::NOTIFY_STATUS_NONE,
                'confirmed_at'  => $now = now(),
                'operated_at'   => $now,
            ]);

            foreach ($transaction->transactionFees as $transactionFee) {
                $actualFee = $transactionFee->fee;
                $actualProfit = $transactionFee->profit;

                TransactionFee::where([
                    'user_id'        => $transactionFee->user_id,
                    'transaction_id' => $transactionFee->transaction_id,
                    'thirdchannel_id' => $transactionFee->thirdchannel_id,
                ])->update([
                    'actual_fee' => $actualFee,
                    'actual_profit' => $actualProfit,
                ]);
            }

            $transaction->load('transactionFees');

            // 交易從支付超時時改為成功
            if ($transaction->type === Transaction::TYPE_PAUFEN_TRANSACTION && $fromPayingTimedOut) {
                // 如果已"退還預扣款，需要補扣碼商餘額
                if (!$transaction->refundYet() && !$this->cancelPaufen) {
                    $orderNumber = $transaction->to()->first()->role === User::ROLE_MERCHANT ? $transaction->order_number : $transaction->system_order_number;
                    throw_if(
                        $this->bcMath->lt($transaction->fromWallet->available_balance, $transaction->floating_amount),
                        new InsufficientAvailableBalance()
                    );
                    $this->wallet->withdraw($transaction->fromWallet, $transaction->floating_amount, $orderNumber, $transactionType = 'transaction');
                }
            }

            $this->settlementService->settleProfit($transaction);

            $this->settleToWallet($transaction);

            $this->shouldMarkParentAsSuccessful($transaction, $operator, $autoSuccess);

            return $transaction->refresh();
        });

        if ($transaction->type === Transaction::TYPE_PAUFEN_TRANSACTION && $this->featureToggleRepository->enabled(FeatureToggle::NOTIFY_NON_SUCCESS_USER_CHANNEL_ACCOUNT)) {
            $userChannelAccountId = $transaction->from_channel_account_id;
            $payingTimeoutCacheKey = "user-channel-account-paying-timeout-$userChannelAccountId";

            Cache::forget($payingTimeoutCacheKey);
        }

        if ($transaction->type === Transaction::TYPE_PAUFEN_TRANSACTION) {
            // 累積日/月限額
            if ($transaction->from_channel_account_id) {
                $this->userChannelAccountUtil->updateTotal($transaction->from_channel_account_id, $transaction->floating_amount);
            }
        }
        // 如果有出款帳號，成功話 出款帳號扣除額度
        if ($transaction->to_channel_account_id) {
            $account = $transaction->toChannelAccount;
            if ($account) { // 防止突然被刪卡後出現 Error
                $account->updateBalanceByTransaction($transaction);
            }
        }
        // 如果有收款帳號，成功話 收款帳號累加額度
        if ($transaction->from_channel_account_id) {
            $account = $transaction->fromChannelAccount;
            if ($account) { // 防止突然被刪卡後出現 Error
                $account->updateBalanceByTransaction($transaction);
            }
        }

        if ($transaction->notify_url) {
            NotifyTransaction::dispatch($transaction);
        }

        return $transaction;
    }

    /**
     * 結算給付款方
     *
     * @param  Transaction  $transaction
     * @return Transaction
     */
    public function settleToWallet(Transaction $transaction, $memo = null)
    {
        return $this->settlementService->settleToWallet($transaction, $memo);
    }

    private function shouldMarkParentAsSuccessful(
        Transaction $transaction,
        ?User $operator = null,
        $autoSuccess = false
    ) {
        if ($transaction->isWithdrawSeparatedChild()) {
            $parentTransaction = Transaction::lockForUpdate()->findOrFail($transaction->parent_id);

            $allChildrenSucceed = ($parentTransaction->children()
                ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                ->count() === $parentTransaction->children()->count()
            );

            if ($allChildrenSucceed) {
                $parentTransaction->update([
                    'operator_id'   => optional($operator)->getKey(),
                    'status'        => $autoSuccess ? Transaction::STATUS_SUCCESS : Transaction::STATUS_MANUAL_SUCCESS,
                    'actual_amount' => $parentTransaction->floating_amount,
                    'notify_status' => $parentTransaction->notify_url ? Transaction::NOTIFY_STATUS_PENDING : Transaction::NOTIFY_STATUS_NONE,
                    'confirmed_at'  => $now = now(),
                    'operated_at'   => $now,
                ]);

                if ($parentTransaction->notify_url) {
                    NotifyTransaction::dispatch($parentTransaction);
                }
            }
        }
    }

    public function markPaufenTransactionAsPartialSuccess(
        Transaction $transaction,
        $amount,
        ?User $operator = null,
        $autoSuccess = false
    ) {
        $transaction = DB::transaction(function () use ($transaction, $autoSuccess, $amount, $operator) {
            $this->markAsFailed($transaction, $operator);

            $transaction = $this->separationService->createChildPaufenTransaction($transaction, $amount, $operator, $autoSuccess);

            $transaction->load('transactionFees');

            if (!$transaction->thirdchannel_id && !$this->cancelPaufen) {
                throw_if(
                    $this->bcMath->lt($transaction->fromWallet->available_balance, $transaction->amount),
                    new InsufficientAvailableBalance()
                );

                $this->wallet->withdraw($transaction->fromWallet, $transaction->amount, $transaction->order_number, $transactionType = 'transaction');
            }
            $this->settlementService->settleProfit($transaction);

            $this->settleToWallet($transaction);

            if ($transaction->from_channel_account_id) {
                $this->userChannelAccountUtil->updateTotal($transaction->from_channel_account_id, $transaction->floating_amount);
            }

            return $transaction->refresh();
        });

        if ($transaction->notify_url) {
            NotifyTransaction::dispatch($transaction);
        }

        return $transaction;
    }

    public function markAsFailed(
        Transaction $transaction,
        ?User $operator = null,
        $note = null,
        bool $shouldLock = true
    ) {
        throw_if(in_array($transaction->status, [Transaction::STATUS_FAILED]), new InvalidStatusException());

        $result = DB::transaction(function () use ($transaction, $operator, $note, $shouldLock) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->getKey());
            $originStatus = $transaction->status;

            throw_if(
                !in_array($transaction->status, [
                    Transaction::STATUS_PENDING_REVIEW,
                    Transaction::STATUS_PAYING,
                    Transaction::STATUS_PAYING_TIMED_OUT,
                    Transaction::STATUS_RECEIVED,
                    Transaction::STATUS_THIRD_PAYING,
                    Transaction::STATUS_MATCHING,
                    Transaction::STATUS_SUCCESS,
                    Transaction::STATUS_MANUAL_SUCCESS
                ]),
                new InvalidStatusException()
            );

            $this->stateValidator->validateNotSeparatedParent($transaction);

            if ($shouldLock) {
                $this->stateValidator->validateLockBeforeUpdate($transaction, $operator);
            }

            $this->stateValidator->validateChildCanBeFailed($transaction);

            $transaction->update([
                'operator_id'   => optional($operator)->getKey(),
                'confirmed_at'  => null,
                'status'        => Transaction::STATUS_FAILED,
                'notify_status' => $transaction->notify_url ? Transaction::NOTIFY_STATUS_PENDING : Transaction::NOTIFY_STATUS_NONE,
                'operated_at'   => now(),
            ]);

            foreach ($transaction->transactionFees as $transactionFee) {
                TransactionFee::where([
                    'user_id'        => $transactionFee->user_id,
                    'transaction_id' => $transactionFee->transaction_id,
                    'thirdchannel_id' => $transactionFee->thirdchannel_id,
                ])->update([
                    'actual_fee' => 0,
                    'actual_profit' => 0,
                ]);
            }

            if (isset($note) && !empty($note)) {
                TransactionNote::create([
                    'transaction_id' => $transaction->id,
                    'user_id' => 0,
                    'note' => $note
                ]);
            }

            switch ($transaction->type) {
                case Transaction::TYPE_PAUFEN_TRANSACTION:
                    $needRefund = $transaction->from && $transaction->refundYet();
                    // 只有原本是等待支付中的跑分交易且未退款，標記為失敗時需退款
                    // 且非免签模式
                    if ($needRefund && !$this->cancelPaufen) {
                        $fromWallet = $transaction->fromWallet;
                        $fromUser = User::find($fromWallet->user_id);
                        $orderNumber = $fromUser->role == User::ROLE_MERCHANT ? $transaction->order_number : $transaction->system_order_number;
                        $this->wallet->withdrawRollback($transaction->fromWallet, $transaction->floating_amount, $orderNumber, 'transaction');
                        $transaction->update(['refunded_at' => now()]);
                    }

                    // 代收成功變失敗時
                    $this->settlementService->rollbackPaufenSettlement($transaction, $originStatus);

                    DevicePayingTransaction::where([
                        'user_channel_account_id' => $transaction->from_channel_account_id,
                        'transaction_id'          => $transaction->getKey(),
                    ])->delete();
                    break;
                case Transaction::TYPE_PAUFEN_WITHDRAW:
                case Transaction::TYPE_NORMAL_WITHDRAW:
                case Transaction::TYPE_INTERNAL_TRANSFER:
                    // 手續費退款
                    $fees = $transaction->transactionFees;
                    $isMerchant = $transaction->from->role == User::ROLE_MERCHANT;
                    $userWithdrawFee = $fees->firstWhere('user_id', $transaction->from_id);

                    if ($userWithdrawFee) {
                        $this->wallet->withdrawRollback(
                            $userWithdrawFee->user->wallet,
                            $this->bcMath->add($transaction->amount, $userWithdrawFee->fee),
                            $isMerchant ? $transaction->order_number : $transaction->system_order_number,
                            $transactionType = 'withdraw',
                            ($transaction->sub_type == Transaction::SUB_TYPE_WITHDRAW_PROFIT) ? 'profit' : 'balance'
                        );
                    }
                    $transaction->update(['refunded_at' => now()]);

                    // 代付成功變失敗時
                    $this->settlementService->rollbackWithdrawSettlement($transaction, $originStatus);

                    // 扣除出款帳號的日/月限額
                    if ($transaction->toChannelAccount) {
                        $amount = $this->bcMath->add($transaction->floating_amount, data_get($transaction->from_channel_account, 'extra_withdraw_fee', 0));
                        $this->userChannelAccountUtil->updateTotalRollback($transaction->to_channel_account_id, $amount, true);
                    }

                    $this->shouldMarkParentAsFailed($transaction, $operator);
                    break;
            }

            return $transaction;
        });

        if ($result->notify_url) {
            NotifyTransaction::dispatch($result);
        }

        return $result;
    }

    public function markAsRefunded(
        Transaction $transaction,
        ?User $operator = null,
        string $note = null,
        bool $shouldLock = true
    ) {
        $transaction = DB::transaction(function () use ($transaction, $operator, $note, $shouldLock) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->getKey());

            throw_if(
                !in_array($transaction->status, [
                    Transaction::STATUS_PENDING_REVIEW,
                    Transaction::STATUS_PAYING,
                    Transaction::STATUS_PAYING_TIMED_OUT
                ]) && $transaction->refunded_at,
                new TransactionRefundedException()
            );

            if ($shouldLock) {
                $this->stateValidator->validateLockBeforeUpdate($transaction, $operator);
            }

            // 目前只有銷單(跑分交易)需要手動退回預扣
            switch ($transaction->type) {
                case Transaction::TYPE_PAUFEN_TRANSACTION:
                    if ($transaction->refundYet()) {
                        $this->wallet->withdrawRollback(
                            $transaction->fromWallet,
                            $transaction->floating_amount,
                            $transaction->system_order_number,
                            $transactionType = 'transaction'
                        );
                        $transaction->update([
                            'refunded_by_id' => optional($operator)->getKey(),
                            'refunded_at' => now()
                        ]);
                    }
            }

            return $transaction->refresh();
        });

        return  $transaction;
    }

    private function shouldMarkParentAsFailed(Transaction $transaction, ?User $operator = null)
    {
        if ($transaction->isWithdrawSeparatedChild()) {
            $parentTransaction = Transaction::lockForUpdate()->findOrFail($transaction->parent_id);

            $allChildrenFailed = ($parentTransaction->children()
                ->where('status', Transaction::STATUS_FAILED)
                ->count() === $parentTransaction->children()->count()
            );

            if ($allChildrenFailed) {
                $parentTransaction->update([
                    'operator_id'   => optional($operator)->getKey(),
                    'confirmed_at'  => null,
                    'status'        => Transaction::STATUS_FAILED,
                    'notify_status' => $parentTransaction->notify_url ? Transaction::NOTIFY_STATUS_PENDING : Transaction::NOTIFY_STATUS_NONE,
                    'operated_at'   => now(),
                ]);

                $parentTransaction->refresh();

                if ($parentTransaction->notify_url) {
                    NotifyTransaction::dispatch($parentTransaction);
                }
            }
        }

        return $transaction;
    }

    public function rollbackAsPaying(Transaction $deposit, User $operator)
    {
        throw_if(
            $deposit->type !== Transaction::TYPE_PAUFEN_WITHDRAW,
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        DB::transaction(function () use ($deposit, $operator) {
            $updatedRow = Transaction::whereIn(
                'status',
                [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]
            )
                ->where([
                    'id'                => $deposit->getKey(),
                    'type'              => Transaction::TYPE_PAUFEN_WITHDRAW,
                    'to_wallet_settled' => false,
                ])
                ->update([
                    'operator_id'                 => optional($operator)->getKey(),
                    'status'                      => Transaction::STATUS_PAYING,
                    'actual_amount'               => 0,
                    'notify_status'               => Transaction::NOTIFY_STATUS_NONE,
                    'confirmed_at'                => null,
                    'operated_at'                 => now(),
                    'to_wallet_should_settled_at' => null,
                ]);

            throw_if($updatedRow !== 1, new RaceConditionException());

            $this->settlementService->rollbackProfit($deposit);
        });

        return $deposit->refresh();
    }

    public function separateWithdraw(Transaction $withdraw, Collection $childWithdraws, bool $shouldLock = true)
    {
        return $this->separationService->separateWithdraw(
            $withdraw,
            $childWithdraws,
            $shouldLock,
            fn (Transaction $tx, ?string $note, bool $lock, bool $keep) => $this->markAsNormalWithdraw($tx, $note, $lock, $keep)
        );
    }

    public function markAsNormalWithdraw(
        Transaction $transaction,
        ?string $note = null,
        bool $shouldLock = true,
        bool $keepLock = false
    ) {
        $transaction = DB::transaction(function () use ($transaction, $note, $shouldLock, $keepLock) {
            $transaction = Transaction::lockForUpdate()->findOrFail($transaction->getKey());

            abort_if(
                !in_array($transaction->type, [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW]),
                Response::HTTP_BAD_REQUEST,
                '订单类型不正确'
            );

            abort_if(
                $shouldLock
                    && !$transaction->locked,
                Response::HTTP_BAD_REQUEST,
                '请先锁定'
            );


            abort_if(
                $shouldLock
                    && !$transaction->locked_by_id == auth()->id()
                    && !auth()->user()->isAdmin(),
                Response::HTTP_BAD_REQUEST,
                '非锁定人无法操作'
            );

            abort_if(
                !in_array(
                    $transaction->status,
                    [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]
                ),
                Response::HTTP_BAD_REQUEST,
                '目前状态无法转为系统出'
            );

            $transaction->update([
                'locked_at'             => $keepLock ? $transaction->locked_at : null,
                'locked_by_id'          => $keepLock ? $transaction->locked_by_id : null,
                'to_id'                 => 0,
                'to_wallet_id'          => null,
                'type'                  => Transaction::TYPE_NORMAL_WITHDRAW,
                'status'                => Transaction::STATUS_PAYING,
                'to_account_mode'       => null,
                'to_channel_account'    => [],
                'note'                  => $note ?? $transaction->note,
                'certificate_file_path' => null,
                'matched_at'            => null,
            ]);

            $transaction->certificateFiles()->delete();

            $transaction->transactionFees()->whereHas('user', function (Builder $users) {
                $users->where('role', User::ROLE_PROVIDER);
            })->delete();

            return $transaction->refresh();
        });

        // USDT 自營出款：狀態變為 PAYING 後自動發送鏈上交易
        if (
            !$keepLock
            && $transaction->channel_code === Channel::CODE_USDT
            && !$transaction->thirdchannel_id
            && $transaction->from_channel_account_id
        ) {
            ProcessUsdtWithdraw::dispatch($transaction->id);
        }

        return $transaction;
    }
}
