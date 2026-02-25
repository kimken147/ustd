<?php

namespace App\Services\Transaction;

use App\Exceptions\RaceConditionException;
use App\Models\Device;
use App\Models\DevicePayingTransaction;
use App\Models\DeviceRegularCustomer;
use App\Models\FeatureToggle;
use App\Models\MatchingDepositReward;
use App\Models\Transaction;
use App\Models\TransactionFee;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletHistory;
use App\Repository\FeatureToggleRepository;
use App\Utils\BCMathUtil;
use App\Utils\UserChannelAccountUtil;
use App\Utils\WalletUtil;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TransactionSettlementService
{
    private $cancelPaufen;

    public function __construct(
        private WalletUtil $wallet,
        private BCMathUtil $bcMath,
        private FeatureToggleRepository $featureToggleRepository,
        private UserChannelAccountUtil $userChannelAccountUtil
    ) {
        $this->cancelPaufen = $this->featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM);
    }

    // --- 正向結算 ---

    /**
     * 計算所有利潤
     */
    public function settleProfit(Transaction $transaction): void
    {
        $this->getNonMerchantFees($transaction)
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

                $isMerchant = $transactionFee->user->role == User::ROLE_MERCHANT;

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
     */
    public function settleToWallet(Transaction $transaction, $memo = null): Transaction
    {
        if (!$transaction->toWalletShouldSettledNow()) {
            return $transaction;
        }

        DB::transaction(function () use ($transaction, $memo) {
            $isProviderCreditMode = $transaction->to && $transaction->to->account_mode == User::ACCOUNT_MODE_CREDIT;

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

    // --- 反向回滾 ---

    /**
     * 回滾利潤（rollbackAsPaying 的 fee 邏輯）
     */
    public function rollbackProfit(Transaction $transaction): void
    {
        $this->getNonMerchantFees($transaction)
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
                    /** @var Wallet $targetWallet */
                    $targetWallet = $transaction->fromWallet;
                }

                $this->wallet->withdraw(
                    $targetWallet,
                    $transactionFee->actual_profit,
                    $transaction->order_number,
                    $transactionType = 'transaction'
                );
            });
    }

    /**
     * 代收成功變失敗時的回滾
     */
    public function rollbackPaufenSettlement(Transaction $transaction, string $originStatus): void
    {
        if (!in_array($originStatus, [Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_SUCCESS])) {
            return;
        }

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

    /**
     * 代付成功變失敗時的回滾
     */
    public function rollbackWithdrawSettlement(Transaction $transaction, string $originStatus): void
    {
        if (!in_array($originStatus, [Transaction::STATUS_MANUAL_SUCCESS, Transaction::STATUS_SUCCESS])) {
            return;
        }

        $fees = $transaction->transactionFees;

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

    // --- DRY 整合 ---

    /**
     * 過濾出非商戶的 transaction fees
     */
    private function getNonMerchantFees(Transaction $transaction): Collection
    {
        return $transaction
            ->transactionFees
            ->filter(function (TransactionFee $transactionFee) use ($transaction) {
                return $transactionFee->user_id && !in_array($transactionFee->user_id, [
                    $transaction->to_id, // except merchant
                ]);
            });
    }
}
