<?php

namespace App\Services\Transaction;

use App\Exceptions\ChildWithdrawCannotSeparateException;
use App\Exceptions\DifferentChildWithdrawStatusException;
use App\Exceptions\InvalidChildWithdrawAmountException;
use App\Exceptions\InvalidMaxSeparateWithdrawCountException;
use App\Exceptions\InvalidMinSeparateWithdrawCountException;
use App\Exceptions\InvalidStatusException;
use App\Exceptions\OnlyMerchantCanSeparateWithdrawException;
use App\Exceptions\PaufenTransactionHasBeenLockedException;
use App\Exceptions\RaceConditionException;
use App\Exceptions\SeparatedWithdrawShouldCompleteChildrenException;
use App\Exceptions\SeparateWithdrawTotalAmountNotMatchException;
use App\Exceptions\TransactionLockerNotYouException;
use App\Exceptions\TransactionRefundedException;
use App\Exceptions\TransactionShouldLockBeforeUpdateException;
use App\Jobs\NotifyTransaction;
use App\Models\Channel;
use App\Models\Device;
use App\Models\DevicePayingTransaction;
use App\Models\DeviceRegularCustomer;
use App\Models\FeatureToggle;
use App\Models\MatchingDepositReward;
use App\Models\MerchantThirdChannel;
use App\Models\ThirdChannel;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\TransactionNote;
use App\Models\TransactionReward;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Models\Wallet;
use App\Models\WalletHistory;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\BankCardTransferObject;
use App\Utils\InsufficientAvailableBalance;
use App\Utils\TransactionFactory;
use App\Utils\UserChannelAccountUtil;
use App\Utils\WalletUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class TransactionStatusService
{
    /**
     * @var BankCardTransferObject
     */
    private $bankCardTransferObject;

    /**
     * @var BCMathUtil
     */
    private $bcMath;

    /**
     * @var FeatureToggleRepository
     */
    private $featureToggleRepository;

    /**
     * @var TransactionFactory
     */
    private $transactionFactory;

    /**
     * @var WalletUtil
     */
    private $wallet;

    /**
     * @var UserChannelAccountUtil
     */
    private $userChannelAccountUtil;

    private $cancelPaufen;

    public function __construct(
        WalletUtil $wallet,
        BCMathUtil $bcMath,
        FeatureToggleRepository $featureToggleRepository,
        TransactionFactory $transactionFactory,
        BankCardTransferObject $bankCardTransferObject,
        UserChannelAccountUtil $userChannelAccountUtil
    ) {
        $this->wallet = $wallet;
        $this->bcMath = $bcMath;
        $this->featureToggleRepository = $featureToggleRepository;
        $this->transactionFactory = $transactionFactory;
        $this->bankCardTransferObject = $bankCardTransferObject;
        $this->userChannelAccountUtil = $userChannelAccountUtil;
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

        if ($transaction->channel_code == Channel::CODE_GCASH) {
            Redis::del($transaction->id . ':gcash:daifu:mpin', $transaction->id . ':gcash:daifu:pay');
        }

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

            $this->transactionFactory->createWithdrawFees($transaction, $transaction->from, $transaction->sub_type == Transaction::SUB_TYPE_AGENCY_WITHDRAW);

            if (!empty($provider)) {
                $this->transactionFactory->createDepositFees($transaction, $provider, false);
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
                'matched_at'         => !empty($provider) ? now() : null,
                'thirdchannel_id'    => $thirdChannel->id
            ]);

            // 變成三方代付後，要刪除原本手續費
            $transaction->transactionFees()->delete();

            $this->transactionFactory->createWithdrawFees($transaction, $transaction->from, $transaction->sub_type == Transaction::SUB_TYPE_AGENCY_WITHDRAW);

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

        $this->paufenTransactionLocked($transaction, $operator);
        if ($shouldLock) {
            $this->shouldLockBeforeUpdate($transaction, $operator);
        }

        $this->separatedWithdrawCannotBeUpdateDirectly($transaction);
        $this->childWithdrawCanBeUpdatedToSuccess($transaction);

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

            $this->settleTransactionProfit($transaction);

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

    private function paufenTransactionLocked(Transaction $transaction, ?User $operator = null)
    {
        // 交易鎖定後只有鎖定人操作，沒鎖定的話管理員及對應碼商可以操作
        throw_if(
            $transaction->isPaufenTransaction() && $transaction->locked && (!$operator || !$transaction->lockedBy->is($operator)),
            new PaufenTransactionHasBeenLockedException()
        );
    }

    private function shouldLockBeforeUpdate(Transaction $transaction, ?User $operator = null)
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

    private function separatedWithdrawCannotBeUpdateDirectly(Transaction $transaction)
    {
        throw_if(
            $transaction->isWithdraw() && $transaction->children()->exists(),
            new SeparatedWithdrawShouldCompleteChildrenException()
        );
    }

    private function childWithdrawCanBeUpdatedToSuccess(Transaction $transaction)
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

    /**
     * 計算所有利潤
     *
     * @param  Transaction  $transaction
     */
    private function settleTransactionProfit(Transaction $transaction)
    {
        $transaction
            ->transactionFees
            ->filter(function (TransactionFee $transactionFee) use ($transaction) {
                return $transactionFee->user_id && !in_array($transactionFee->user_id, [
                    $transaction->to_id, // except merchant
                ]);
            })
            ->each(function (TransactionFee $transactionFee) use ($transaction) {
                // 無實際收益不返佣
                if (!$this->bcMath->gtZero($transactionFee->actual_profit)) {
                    return;
                }

                // 信用線不返佣
                if ($transaction->isPaufenTransaction() && $transactionFee->creditModeEnabled()) {
                    return;
                }

                $targetWallet = $transactionFee->user->wallet;

                // 押金線只返佣給頭
                if ($transaction->isPaufenTransaction() && $transactionFee->depositModeEnabled()) {
                    $targetWallet = $transaction->fromWallet;
                }

                $isMerchant = $transactionFee->user->role == USER::ROLE_MERCHANT;

                // 商户: 红利要加到余额，码商: 红利要加到红利
                $amount = $isMerchant ? $transactionFee->actual_profit : '0.00';
                $profit = $isMerchant ? '0.00' : $transactionFee->actual_profit;

                $this->wallet->deposit(
                    $targetWallet,
                    $amount,
                    $profit,
                    $isMerchant ? $transaction->order_number : $transaction->system_order_number
                );
            });
    }

    /**
     * 結算給付款方
     *
     * @param  Transaction  $transaction
     * @return Transaction
     */
    public function settleToWallet(Transaction $transaction, $memo = null)
    {
        if (!$transaction->toWalletShouldSettledNow()) {
            return $transaction;
        }

        DB::transaction(function () use ($transaction, $memo) {
            $isProviderCreditMode = $transaction->to && $transaction->to->account_mode == USER::ACCOUNT_MODE_CREDIT;

            switch ($transaction->type) {
                case Transaction::TYPE_PAUFEN_WITHDRAW:
                    if ($this->featureToggleRepository->enabled(FeatureToggle::MATCHING_DEPOSIT_REWARD)) {
                        $matchingDepositReward = MatchingDepositReward::where('min_amount', '<=', $transaction->amount)
                            ->where('max_amount', '>=', $transaction->amount)
                            ->first();

                        if ($matchingDepositReward && !$isProviderCreditMode) {
                            $this->wallet->matchingDepositReward(
                                $transaction,
                                $matchingDepositReward
                            );
                        }
                    }
                    // 不用 break，因為下方一般提現邏輯與跑分提現一樣
                case Transaction::TYPE_NORMAL_DEPOSIT:
                    $user = $transaction->to()->first();
                    $isMerchant = $user->role === User::ROLE_MERCHANT;
                    $amount = $transaction->actual_amount;
                    $profit = $transaction->actual_profit;
                    $note = $isMerchant ? $transaction->order_number : $transaction->system_order_number;

                    if ($isProviderCreditMode) {
                        $note = "{$note}－信用模式不加{$amount}点";
                        $amount = 0;
                        $profit = 0;
                    }

                    if ($isProviderCreditMode || !$this->cancelPaufen) {
                        $this->wallet->deposit(
                            $transaction->toWallet,
                            $amount,
                            $profit,
                            $note,
                            $transaction->deduct_frozen_balance // 只有優質充值可以扣除凍結餘額
                        );
                    }
                    break;
                case Transaction::TYPE_PAUFEN_TRANSACTION:
                    $merchantTransactionFee = $transaction
                        ->transactionFees
                        ->where('user_id', $transaction->to_id)
                        ->first();

                    $isMerchant = $merchantTransactionFee->user->role == User::ROLE_MERCHANT;

                    $amount = $this->bcMath->sub($transaction->amount, $merchantTransactionFee->actual_fee);
                    $profit = $isMerchant ? 0 : $merchantTransactionFee->actual_fee; // 商戶不加紅利
                    $orderNumber = $isMerchant ? $transaction->order_number : $transaction->system_order_number;
                    $memo ? $note = $orderNumber . " " . $memo : $note = $orderNumber;

                    if ($isProviderCreditMode) {
                        $note = "{$orderNumber}－信用模式不加{$amount}点";
                        $amount = 0;
                        $profit = 0;
                    }

                    $this->wallet->deposit(
                        $merchantTransactionFee->user->wallet,
                        $amount,
                        $profit,
                        $note
                    );

                    if (!$this->cancelPaufen) {
                        DevicePayingTransaction::where([
                            'user_channel_account_id' => $transaction->from_channel_account_id,
                            'transaction_id'          => $transaction->getKey(),
                        ])->delete();

                        $device = Device::where([
                            'user_id' => $transaction->from_id,
                            'name'    => $transaction->from_device_name,
                        ])->first();

                        if ($device) {
                            $now = now();

                            DeviceRegularCustomer::insertOnDuplicateKey(
                                [
                                    'device_id'  => $device->getKey(),
                                    'client_ipv4' => ip2long($transaction->client_ipv4),
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ],
                                [
                                    'updated_at' => $now
                                ]
                            );
                        }
                    }

                    break;
            }

            $updatedRow = Transaction::where([
                'id'                          => $transaction->getKey(),
                'to_wallet_settled'           => false,
                'to_wallet_should_settled_at' => $transaction->to_wallet_should_settled_at,
            ])->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                ->update([
                    'to_wallet_settled' => true,
                ]);

            throw_if($updatedRow !== 1, new RaceConditionException());
        });

        return $transaction->refresh();
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

            $transaction = $this->createChildPaufenTransaction($transaction, $amount, $operator, $autoSuccess);

            $transaction->load('transactionFees');

            if (!$transaction->thirdchannel_id && !$this->cancelPaufen) {
                throw_if(
                    $this->bcMath->lt($transaction->fromWallet->available_balance, $transaction->amount),
                    new InsufficientAvailableBalance()
                );

                $this->wallet->withdraw($transaction->fromWallet, $transaction->amount, $transaction->order_number, $transactionType = 'transaction');
            }
            $this->settleTransactionProfit($transaction);

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

            $this->separatedWithdrawCannotBeUpdateDirectly($transaction);

            if ($shouldLock) {
                $this->shouldLockBeforeUpdate($transaction, $operator);
            }

            $this->childWithdrawCanBeUpdatedToFail($transaction);

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
                    if (in_array($originStatus, [Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_SUCCESS])) {
                        $fees = $transaction->transactionFees;
                        if (!$this->cancelPaufen && $transaction->from) {
                            // 碼商代理要扣除利潤
                            $fromAncestors = $transaction->from->ancestors;
                            foreach ($fromAncestors as $from) {
                                $fee = $fees->firstWhere('user_id', $from->id);
                                $this->wallet->depositRollback($from->wallet, 0, $fee->actual_profit, $transaction->system_order_number);
                            }

                            // 碼商扣除利潤
                            $fee = $fees->firstWhere('user_id', $transaction->from_id);
                            $this->wallet->depositRollback($transaction->fromWallet, 0, $fee->actual_profit, $transaction->system_order_number);
                            // 上面 $needRefund && !$this->cancelPaufen 條件，已加回交易金額，不需再加回
                        }

                        // 商戶代理要扣除利潤
                        $toAncestors = $transaction->to->ancestors;
                        foreach ($toAncestors as $to) {
                            $fee = $fees->firstWhere('user_id', $to->id);
                            $this->wallet->depositRollback($to->wallet, $fee->actual_profit, 0, $transaction->order_number);
                        }
                        // 商戶扣除(交易金額 - 手續費)
                        $fee = $fees->firstWhere('user_id', $transaction->to_id);
                        $merchantFee = $this->bcMath->sub($transaction->amount, $fee->actual_fee);
                        $this->wallet->depositRollback($transaction->to->wallet, $merchantFee, 0, $transaction->order_number);

                        $account = $transaction->fromChannelAccount;
                        if ($account) { // 防止突然被刪卡後出現 Error
                            $account->updateBalanceByTransaction($transaction, true);
                        }

                        // 要扣除收款的 日/月限額
                        if ($transaction->from_channel_account_id) {
                            $this->userChannelAccountUtil->updateTotalRollback($transaction->from_channel_account_id, $transaction->floating_amount);
                        }
                    }

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
                    if (in_array($originStatus, [Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_SUCCESS])) {
                        // 扣除上級手續費
                        if ($transaction->from && $this->featureToggleRepository->enabled(FeatureToggle::AGENT_WITHDRAW_PROFIT)) {
                            $fromAncestors = $transaction->from->ancestors;
                            foreach ($fromAncestors as $from) {
                                $fee = $fees->firstWhere('user_id', $from->id);
                                $this->wallet->depositRollback($from->wallet, $fee->actual_profit, 0, $transaction->order_number);
                            }
                        }
                        if ($transaction->to) {
                            $note = $transaction->system_order_number;
                            $rewardAmount = 0;
                            $matchingDepositReward = WalletHistory::where("user_id", $transaction->to_id)
                                ->where("type", WalletHistory::TYPE_MATCHING_DEPOSIT_REWARD)
                                ->where("note", "like", "%$transaction->system_order_number%")
                                ->first();
                            $frozenAmountRecord = WalletHistory::where("user_id", $transaction->to_id)
                                ->where("type", WalletHistory::TYPE_DEPOSIT_DEDUCT_FROZEN_BALANCE)
                                ->where("note", "like", "%$transaction->system_order_number%")
                                ->first();
                            if ($matchingDepositReward) {
                                $rewardAmount = $matchingDepositReward->delta["profit"] ?? 0;
                                $note = "$note 快充奖励($rewardAmount)";
                            }
                            if ($frozenAmountRecord) {
                                $frozenAmount = -$frozenAmountRecord->delta["frozen_balance"];
                                $note = "$note 冻结金额($frozenAmount)";
                            }
                            $this->wallet->depositRollback($transaction->toWallet, $transaction->amount, $rewardAmount, $note, $frozenAmountRecord ? $frozenAmount : 0);
                        }
                        // 成功變失敗時才能加回餘額
                        if ($transaction->toChannelAccount) {
                            $transaction->toChannelAccount->updateBalanceByTransaction($transaction, true);
                        }
                    }

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
                $this->shouldLockBeforeUpdate($transaction, $operator);
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

    private function childWithdrawCanBeUpdatedToFail(Transaction $transaction)
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

    private function createChildPaufenTransaction(
        Transaction $transaction,
        $amount,
        ?User $operator = null,
        $autoSuccess = false
    ) {
        /** @var Transaction $childTransaction */
        $childTransaction = Transaction::create([
            'parent_id'                    => $transaction->getKey(),
            'from_id'                      => $transaction->from_id,
            'from_wallet_id'               => $transaction->from_wallet_id,
            'to_id'                        => $transaction->to_id,
            'to_wallet_id'                 => $transaction->to_wallet_id,
            'locked_by_id'                 => $transaction->locked_by_id,
            'thirdchannel_id'              => $transaction->thirdchannel_id,
            'from_channel_account_id'      => $transaction->from_channel_account_id,
            'operator_id'                  => optional($operator)->getKey(),
            'client_ipv4'                  => $transaction->client_ipv4,
            'type'                         => $transaction->type,
            'status'                       => $autoSuccess ? Transaction::STATUS_SUCCESS : Transaction::STATUS_MANUAL_SUCCESS,
            'notify_status'                => Transaction::NOTIFY_STATUS_NONE,
            'from_account_mode'            => $transaction->from_account_mode,
            'to_account_mode'              => $transaction->to_account_mode,
            'from_channel_account'         => $transaction->from_channel_account,
            'to_channel_account'           => $transaction->to_channel_account,
            'amount'                       => $amount,
            'floating_amount'              => $amount,
            'actual_amount'                => $amount,
            'channel_code'                 => $transaction->channel_code,
            'from_channel_account_hash_id' => $transaction->from_channel_account_hash_id,
            'order_number'                 => null,
            'note'                         => $transaction->note,
            'notify_url'                   => null,
            'from_device_name'             => $transaction->from_device_name,
            'certificate_file_path'        => $transaction->certificate_file_path,
            'notified_at'                  => null,
            'matched_at'                   => $transaction->matched_at,
            'confirmed_at'                 => $now = now(),
            'locked_at'                    => $transaction->locked_at,
            'operated_at'                  => $now
        ]);

        $partialPercent = $this->bcMath->div($amount, $transaction->amount);

        $transaction->transactionFees->each(function (TransactionFee $transactionFee) use ($childTransaction, $partialPercent) {

            $childTransaction->transactionFees()->create([
                'user_id'         => $transactionFee->user_id,
                'account_mode'    => $transactionFee->account_mode,
                'profit'          => $profit = $this->bcMath->mul($transactionFee->profit, $partialPercent),
                'actual_profit'   => $profit,
                'fee'             => $fee = $this->bcMath->mul($transactionFee->fee, $partialPercent),
                'actual_fee'      => $fee,
                'thirdchannel_id' => $transactionFee->thirdchannel_id
            ]);
        });

        // 如果有出款帳號，成功話 出款帳號扣除額度
        if ($childTransaction->to_channel_account_id) {
            $account = $childTransaction->toChannelAccount;
            if ($account) {
                $account->updateBalanceByTransaction($childTransaction);
            }
        }
        // 如果有收款帳號，成功話 收款帳號累加額度
        if ($childTransaction->from_channel_account_id) {
            $account = $childTransaction->fromChannelAccount;
            if ($account) {
                $account->updateBalanceByTransaction($childTransaction);
            }
        }

        return $childTransaction;
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

            // all providers and merchant agents
            $deposit
                ->transactionFees
                ->filter(function (TransactionFee $transactionFee) use ($deposit) {
                    return $transactionFee->user_id && !in_array($transactionFee->user_id, [
                        $deposit->to_id, // except merchant
                    ]);
                })
                ->each(function (TransactionFee $transactionFee) use ($deposit) {
                    // 無實際收益不返佣
                    if (!$this->bcMath->gtZero($transactionFee->actual_profit)) {
                        return;
                    }

                    // 信用線不返佣
                    if ($deposit->isPaufenTransaction() && $transactionFee->creditModeEnabled()) {
                        return;
                    }

                    $targetWallet = $transactionFee->user->wallet;

                    // 押金線只返佣給頭
                    if (
                        $deposit->isPaufenTransaction()
                        && $transactionFee->depositModeEnabled()
                    ) {
                        /** @var Wallet $targetWallet */
                        $targetWallet = $deposit->fromWallet;
                    }

                    $this->wallet->withdraw(
                        $targetWallet,
                        $transactionFee->actual_profit,
                        $deposit->order_number,
                        $transactionType = 'transaction'
                    );
                });
        });

        return $deposit->refresh();
    }

    public function separateWithdraw(Transaction $withdraw, Collection $childWithdraws, bool $shouldLock = true)
    {

        abort_if(
            $withdraw->type === Transaction::TYPE_PAUFEN_WITHDRAW && $withdraw->to_id,
            Response::HTTP_BAD_REQUEST,
            '该笔跑分提现码商已抢单，请使用「充值管理」确认订单资讯'
        );

        throw_if(
            $withdraw->isChild(),
            new ChildWithdrawCannotSeparateException()
        );

        throw_if(
            $withdraw->from->role !== User::ROLE_MERCHANT,
            new OnlyMerchantCanSeparateWithdrawException()
        );

        throw_if(
            $childWithdraws->count() < 2,
            new InvalidMinSeparateWithdrawCountException()
        );

        throw_if(
            $childWithdraws->count() > 10,
            new InvalidMaxSeparateWithdrawCountException()
        );

        foreach ($childWithdraws as $childWithdraw) {
            throw_if(
                !$this->bcMath->gtZero($childWithdraw['amount']),
                new InvalidChildWithdrawAmountException()
            );
        }

        $totalChildWithdrawAmount = $this->bcMath->sum($childWithdraws->pluck('amount')->toArray());

        throw_if(
            $this->bcMath->notEqual($withdraw->amount, $totalChildWithdrawAmount),
            new SeparateWithdrawTotalAmountNotMatchException()
        );

        return DB::transaction(function () use ($withdraw, $childWithdraws, $shouldLock) {
            /** @var Transaction $withdraw */
            $withdraw = Transaction::lockForUpdate()->findOrFail($withdraw->getKey());

            abort_if(
                $shouldLock
                    && !$withdraw->locked,
                Response::HTTP_BAD_REQUEST,
                __('transaction.You have to lock before doing this')
            );

            abort_if(
                $shouldLock
                    && $withdraw->locked
                    && !$withdraw->lockedBy->is(auth()->user()->realUser()),
                Response::HTTP_BAD_REQUEST,
                __('transaction.Already been locked, you are not allowing to do status update')
            );

            abort_if(
                $withdraw->children()->exists(),
                Response::HTTP_BAD_REQUEST,
                '订单已拆单'
            );

            abort_if(
                !in_array(
                    $withdraw->status,
                    [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED]
                ),
                Response::HTTP_BAD_REQUEST,
                '目前订单状态无法拆单'
            );

            $withdraw = $this->markAsNormalWithdraw($withdraw, null, $shouldLock, true);

            foreach ($childWithdraws as $childWithdraw) {
                $this->transactionFactory = $this->transactionFactory
                    ->fresh()
                    ->bankCard($this->bankCardTransferObject->plain(
                        $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_NAME],
                        $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CARD_NUMBER],
                        $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME],
                        $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_PROVINCE] ?? '',
                        $withdraw->from_channel_account[UserChannelAccount::DETAIL_KEY_BANK_CITY] ?? ''
                    ))
                    ->parent($withdraw);

                switch (data_get($childWithdraw, 'type')) {
                    case Transaction::TYPE_PAUFEN_WITHDRAW:
                        /** @var Transaction $transaction */
                        $this->transactionFactory->amount(data_get($childWithdraw, 'amount'))
                            ->clientIpv4($withdraw->client_ipv4)
                            ->subType($withdraw->sub_type);

                        $childWithdrawModel = $this->transactionFactory->paufenWithdrawFrom(
                            $withdraw->from,
                            false,
                            $withdraw
                        );

                        $providerId = data_get($childWithdraw, 'to_id');

                        // 有指定碼商
                        if ($providerId) {
                            $provider = User::find($providerId);

                            abort_if(!$provider, Response::HTTP_BAD_REQUEST, '查无使用者');
                            abort_if($provider->role !== User::ROLE_PROVIDER, Response::HTTP_BAD_REQUEST, '仅能指定码商');

                            $this->transactionFactory->paufenDepositTo($provider, $childWithdrawModel, $withdraw);
                        }

                        $channelAccountId = data_get($childWithdraw, 'to_channel_account_id');
                        // 有指定出款帳號
                        if ($channelAccountId) {
                            $account = UserChannelAccount::find($channelAccountId);

                            abort_if(!$provider, Response::HTTP_BAD_REQUEST, '查无出款帐号');

                            $this->transactionFactory->paufenDepositToAccount($account, $childWithdrawModel, $withdraw);
                        }

                        break;
                    case Transaction::TYPE_NORMAL_WITHDRAW:
                        $this->transactionFactory
                            ->amount(data_get($childWithdraw, 'amount'))
                            ->clientIpv4($withdraw->client_ipv4)
                            ->subType($withdraw->sub_type);

                        $this->transactionFactory->normalWithdrawFrom($withdraw->from, false, $withdraw);
                        break;
                    default:
                        abort(Response::HTTP_INTERNAL_SERVER_ERROR);
                }
            }

            return $withdraw->refresh();
        });
    }

    public function markAsNormalWithdraw(
        Transaction $transaction,
        ?string $note = null,
        bool $shouldLock = true,
        bool $keepLock = false
    ) {
        return DB::transaction(function () use ($transaction, $note, $shouldLock, $keepLock) {
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
    }
}
