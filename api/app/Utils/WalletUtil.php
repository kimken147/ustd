<?php


namespace App\Utils;

use App\Exceptions\RaceConditionException;
use App\Model\MatchingDepositReward;
use App\Model\Transaction;
use App\Model\TransactionReward;
use App\Model\User;
use App\Model\Wallet;
use App\Model\WalletHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletUtil
{

    /**
     * @var BCMathUtil
     */
    private $bcMath;

    public function __construct(BCMathUtil $bcMath)
    {
        $this->bcMath = $bcMath;
    }

    public function conflictAwaredBalanceUpdate(Wallet $wallet, array $delta, $note = null, $type = WalletHistory::TYPE_SYSTEM_ADJUSTING)
    {
        if ($this->bcMath->ltZero($delta['balance'])) {
            throw_if($this->bcMath->lt($wallet->balance, $this->bcMath->positiveOf($delta['balance'])), new InsufficientBalance());
        }

        throw_if(
            $this->bcMath->ltZero($delta['profit'])
                && $this->bcMath->lt($wallet->profit, $this->bcMath->positiveOf($delta['profit'])),
            new InsufficientProfit()
        );

        throw_if(
            $this->bcMath->ltZero($delta['frozen_balance'])
                && $this->bcMath->lt($wallet->frozen_balance, $this->bcMath->positiveOf($delta['frozen_balance'])),
            new InsufficientAvailableBalance()
        );

        $resultAttributes = [
            'balance'        => $this->bcMath->add($wallet->balance, $delta['balance']),
            'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
            'frozen_balance' => $this->bcMath->add($wallet->frozen_balance, $delta['frozen_balance']),
        ];

        $updated = $wallet->update($resultAttributes);

        if (!$updated) {
            throw new RaceConditionException();
        }

        $walletHistory = WalletHistory::latest()->first();
        $adjustmentNumber = ($walletHistory->id ?? 0) + 1;

        WalletHistory::create([
            'user_id'     => $wallet->user->getKey(),
            'operator_id' => auth()->user() ? auth()->user()->realUser()->getKey() : 0,
            'type'        => $type,
            'delta'       => $delta,
            'result'      => $resultAttributes,
            'note'        => config('wallethistory.system_adjustment_number_prefix') . date('YmdHis') . $adjustmentNumber . " " . $note,
        ]);

        return $wallet->refresh();
    }


    public function deposit(Wallet $wallet, string $amount, $profit, $note = '', bool $deduct_frozen_balance = false)
    {
        if ($amount == 0 && $profit == 0) return true;

        return DB::transaction(function () use ($wallet, $amount, $profit, $note, $deduct_frozen_balance) {
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->getKey());

            if ($deduct_frozen_balance) {
                if ($this->bcMath->gte($wallet->frozen_balance, $amount)) {
                    $delta = [
                        'balance' => '0.00',
                        'profit'  => '0.00',
                        'frozen_balance' =>  $this->bcMath->negativeOf($amount) // 負數表示凍結金額的扣除
                    ];
                } else {
                    $delta = [
                        'balance' => $this->bcMath->sub($amount, $wallet->frozen_balance), // 正數表示扣除凍結金額後，還有餘額則新增可用餘額
                        'profit'  => '0.00',
                        'frozen_balance' => $this->bcMath->negativeOf($wallet->frozen_balance) // 凍結金額全部扣完
                    ];
                }

                $this->conflictAwaredBalanceUpdate($wallet, $delta, $note, WalletHistory::TYPE_DEPOSIT_DEDUCT_FROZEN_BALANCE);
            } else {
                $delta = [
                    'balance'        => $this->bcMath->positiveOf($amount ?: '0.00'),
                    'profit'         => $this->bcMath->positiveOf($profit ?: '0.00'),
                    'frozen_balance' => '0.00',
                ];

                $result = [
                    'balance'        => $this->bcMath->add($wallet->balance, $delta['balance']),
                    'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
                    'frozen_balance' => $this->bcMath->add($wallet->frozen_balance, $delta['frozen_balance'])
                ];

                $updatedRows = $this->updateWallet($wallet, $delta);

                if ($delta['balance'] != '0.00') {
                    $type = WalletHistory::TYPE_DEPOSIT;
                } else {
                    $type = WalletHistory::TYPE_DEPOSIT_PROFIT;
                }

                WalletHistory::create([
                    'user_id'     => $wallet->user->getKey(),
                    'operator_id' => 0, // system
                    'type'        => $type ?? WalletHistory::TYPE_DEPOSIT,
                    'delta'       => $delta,
                    'result'      => $result,
                    'note'        => $note ?? '',
                ]);

                return $updatedRows;
            }
        });
    }

    public function depositRollback(Wallet $wallet, string $amount, $profit, $note = '', $frozen_balance = 0)
    {
        if ($amount == 0 && $profit == 0) return true;

        return DB::transaction(function () use ($wallet, $amount, $profit, $note, $frozen_balance) {
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->getKey());

            throw_if(
                isset($delta['balance'])
                    && $this->bcMath->lt($wallet->available_balance, $this->bcMath->positiveOf($delta['balance'])),
                new InsufficientAvailableBalance()
            );

            throw_if(
                isset($delta['profit'])
                    && $this->bcMath->lt($wallet->profit, $this->bcMath->positiveOf($delta['profit'])),
                new InsufficientProfit()
            );

            $delta = [
                'balance'        => $frozen_balance == 0 ? $this->bcMath->negativeOf($amount ?: '0.00') : 0,
                'profit'         => $this->bcMath->negativeOf($profit ?: '0.00'),
                'frozen_balance' => $frozen_balance,
            ];

            $result = [
                'balance'        => $this->bcMath->add($wallet->balance, $delta['balance']),
                'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
                'frozen_balance' => $this->bcMath->add($wallet->frozen_balance, $delta['frozen_balance'])
            ];

            $updatedRows = $this->updateWallet($wallet, $delta);

            WalletHistory::create([
                'user_id'     => $wallet->user_id,
                'operator_id' => 0, // system
                'type'        => WalletHistory::TYPE_DEPOSIT_ROLLBACK,
                'delta'       => $delta,
                'result'      => $result,
                'note'        => $note ?? '',
            ]);

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

        throw_if(
            isset($delta['balance'])
                && $this->bcMath->ltZero($delta['balance'])
                && $this->bcMath->lt($wallet->available_balance, $this->bcMath->positiveOf($delta['balance'])),
            new InsufficientAvailableBalance()
        );

        throw_if(
            isset($delta['profit'])
                && $this->bcMath->ltZero($delta['profit'])
                && $this->bcMath->lt($wallet->profit, $this->bcMath->positiveOf($delta['profit'])),
            new InsufficientProfit()
        );

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

    public function transactionReward(Transaction $transaction, TransactionReward $transactionReward)
    {
        switch ($transactionReward->reward_unit) {
            case TransactionReward::REWARD_UNIT_SINGLE:
                $reward = $transactionReward->reward_amount;
                break;
            case TransactionReward::REWARD_UNIT_PERCENT:
                $reward = $this->bcMath->mulPercent($transaction->amount, $transactionReward->reward_amount);
                break;
            default:
                abort(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($reward == 0) return true;

        return DB::transaction(function () use ($transaction, $reward, $transactionReward) {
            $wallet = Wallet::lockForUpdate()->findOrFail($transaction->from_wallet_id);

            $delta = [
                'balance'        => '0.00',
                'profit'         => $this->bcMath->positiveOf($reward),
                'frozen_balance' => '0.00',
            ];
            $result = [
                'balance'        => $wallet->balance,
                'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
                'frozen_balance' => $wallet->frozen_balance
            ];

            $updatedRows = $this->updateWallet($wallet, $delta);

            WalletHistory::create([
                'user_id'     => $wallet->user->getKey(),
                'operator_id' => 0, // system
                'type'        => WalletHistory::TYPE_TRANSACTION_REWARD,
                'delta'       => $delta,
                'result'      => $result,
                'note'        => $transaction->order_number . ' ' . $this->formatTransactionRewardForNote($transactionReward),
            ]);

            return $updatedRows;
        });
    }

    public function matchingDepositReward(Transaction $transaction, MatchingDepositReward $matchingDepositReward)
    {
        switch ($matchingDepositReward->reward_unit) {
            case MatchingDepositReward::REWARD_UNIT_SINGLE:
                $reward = $matchingDepositReward->reward_amount;
                break;
            case MatchingDepositReward::REWARD_UNIT_PERCENT:
                $reward = $this->bcMath->mulPercent($transaction->amount, $matchingDepositReward->reward_amount);
                break;
            default:
                abort(Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        if ($reward == 0) return true;

        return DB::transaction(function () use ($transaction, $reward, $matchingDepositReward) {
            $user = $transaction->to()->first();
            $isMerchant = $user->role === User::ROLE_MERCHANT;
            $wallet = Wallet::lockForUpdate()->findOrFail($transaction->to_wallet_id);
            $orderNumber = $isMerchant ? $transaction->order_number : $transaction->system_order_number;

            $delta = [
                'balance'        => '0.00',
                'profit'         => $this->bcMath->positiveOf($reward),
                'frozen_balance' => '0.00',
            ];
            $result = [
                'balance'        => $wallet->balance,
                'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
                'frozen_balance' => $wallet->frozen_balance,
            ];

            $updatedRows = $this->updateWallet($wallet, $delta);

            WalletHistory::create([
                'user_id'     => $wallet->user->getKey(),
                'operator_id' => 0, // system
                'type'        => WalletHistory::TYPE_MATCHING_DEPOSIT_REWARD,
                'delta'       => $delta,
                'result'      => $result,
                'note'        => $orderNumber . ' ' . $this->formatMatchingDepositRewardForNote($matchingDepositReward),
            ]);

            return $updatedRows;
        });
    }

    private function formatTransactionRewardForNote(TransactionReward $transactionReward)
    {
        return implode(' ', [
            '交易奖励',
            $transactionReward->started_at, '~', $transactionReward->ended_at,
            $transactionReward->min_amount, '~', $transactionReward->max_amount,
            ($transactionReward->reward_unit === TransactionReward::REWARD_UNIT_SINGLE) ? "{$transactionReward->reward_amount}/笔" : '',
            ($transactionReward->reward_unit === TransactionReward::REWARD_UNIT_PERCENT) ? "{$transactionReward->reward_amount}%" : '',
        ]);
    }

    private function formatMatchingDepositRewardForNote(MatchingDepositReward $matchingDepositReward)
    {
        return implode(' ', [
            '快充奖励',
            $matchingDepositReward->min_amount, '~', $matchingDepositReward->max_amount,
            ($matchingDepositReward->reward_unit === MatchingDepositReward::REWARD_UNIT_SINGLE) ? "{$matchingDepositReward->reward_amount}/笔" : '',
            ($matchingDepositReward->reward_unit === MatchingDepositReward::REWARD_UNIT_PERCENT) ? "{$matchingDepositReward->reward_amount}%" : '',
        ]);
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

            $fromDelta = [
                'balance'        => $this->bcMath->negativeOf($amount),
                'profit'         => '0.00',
                'frozen_balance' => '0.00',
            ];
            $fromResult = [
                'balance'        => $this->bcMath->add($from->balance, $fromDelta['balance']),
                'profit'         => $from->profit,
                'frozen_balance' => $from->frozen_balance,
            ];

            $fromWalletUpdatedRow = $this->updateWallet($from, $fromDelta);

            WalletHistory::create([
                'user_id'     => $from->user->getKey(),
                'operator_id' => $operator->realUser()->getKey(),
                'type'        => WalletHistory::TYPE_TRANSFER,
                'delta'       => $fromDelta,
                'result'      => $fromResult,
                'note'        => $note . " 转出给 {$to->user->name} {$to->user->username}",
            ]);

            $toDelta = [
                'balance'        => $this->bcMath->positiveOf($amount),
                'frozen_balance' => '0.00',
            ];
            $toResult = [
                'balance'        => $this->bcMath->add($to->balance, $toDelta['balance']),
                'frozen_balance' => $to->frozen_balance,
            ];

            $toWalletUpdatedRow = $this->updateWallet($to, $toDelta);

            WalletHistory::create([
                'user_id'     => $to->user->getKey(),
                'operator_id' => $operator->realUser()->getKey(),
                'type'        => WalletHistory::TYPE_TRANSFER,
                'delta'       => $toDelta,
                'result'      => $toResult,
                'note'        => $note . " 由 {$from->user->name} {$from->user->username} 转入",
            ]);

            return $fromWalletUpdatedRow + $toWalletUpdatedRow;
        });
    }

    public function withdraw(Wallet $wallet, string $amount, $note, string $transactionType, $withdrawType = 'balance')
    {
        if ($amount == 0)  return true;

        return DB::transaction(function () use ($wallet, $amount, $note, $transactionType, $withdrawType) {
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->getKey());

            $balance = ($withdrawType == 'balance') ? $this->bcMath->negativeOf($amount) : '0.00';
            $profit = ($withdrawType == 'profit') ? $this->bcMath->negativeOf($amount) : '0.00';

            $delta = [
                'balance'        => $balance,
                'profit'         => $profit,
                'frozen_balance' => '0.00',
            ];
            $result = [
                'balance'        => $this->bcMath->add($wallet->balance, $delta['balance']),
                'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
                'frozen_balance' => $wallet->frozen_balance,
            ];

            $updatedRows = $this->updateWallet($wallet, $delta);

            WalletHistory::create([
                'user_id'     => $wallet->user->getKey(),
                'operator_id' => 0, // system
                'type'        => ($transactionType == 'transaction') ? WalletHistory::TYPE_WITHHOLD : WalletHistory::TYPE_WITHDRAW,
                'delta'       => $delta,
                'result'      => $result,
                'note'        => $note ?? '',
            ]);

            return $updatedRows;
        });
    }

    public function withdrawNotLock(Wallet $wallet, string $amount, $note, string $transactionType, $withdrawType = 'balance')
    {
        if ($amount == 0)  return true;

        $balance = ($withdrawType == 'balance') ? $this->bcMath->negativeOf($amount) : '0.00';
        $profit = ($withdrawType == 'profit') ? $this->bcMath->negativeOf($amount) : '0.00';

        $delta = [
            'balance'        => $balance,
            'profit'         => $profit,
            'frozen_balance' => '0.00',
        ];
        $result = [
            'balance'        => $this->bcMath->add($wallet->balance, $delta['balance']),
            'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
            'frozen_balance' => $wallet->frozen_balance,
        ];

        $updatedRows = $this->updateWallet($wallet, $delta);

        WalletHistory::create([
            'user_id'     => $wallet->user->getKey(),
            'operator_id' => 0, // system
            'type'        => ($transactionType == 'transaction') ? WalletHistory::TYPE_WITHHOLD : WalletHistory::TYPE_WITHDRAW,
            'delta'       => $delta,
            'result'      => $result,
            'note'        => $note ?? '',
        ]);

        return $updatedRows;
    }

    public function withdrawRollback(Wallet $wallet, string $amount, $note, string $transactionType, $withdrawType = 'balance')
    {
        if ($amount == 0)  return true;

        return DB::transaction(function () use ($wallet, $amount, $note, $transactionType, $withdrawType) {
            $wallet = Wallet::lockForUpdate()->findOrFail($wallet->getKey());

            $balance = ($withdrawType == 'balance') ? $this->bcMath->positiveOf($amount) : '0.00';
            $profit = ($withdrawType == 'profit') ? $this->bcMath->positiveOf($amount) : '0.00';

            $delta = [
                'balance'        => $balance,
                'profit'         => $profit,
                'frozen_balance' => '0.00',
            ];
            $result = [
                'balance'        => $this->bcMath->add($wallet->balance, $delta['balance']),
                'profit'         => $this->bcMath->add($wallet->profit, $delta['profit']),
                'frozen_balance' => $wallet->frozen_balance,
            ];

            $updatedRows = $this->updateWallet($wallet, $delta);

            WalletHistory::create([
                'user_id'     => $wallet->user->getKey(),
                'operator_id' => 0, // system
                'type'        => ($transactionType == 'transaction') ? WalletHistory::TYPE_WITHHOLD_ROLLBACK : WalletHistory::TYPE_WITHDRAW_ROLLBACK,
                'delta'       => $delta,
                'result'      => $result,
                'note'        => $note ?? '',
            ]);

            return $updatedRows;
        });
    }
}
