<?php

namespace App\Utils;

class WalletBalanceCalculator
{
    public function __construct(private readonly BCMathUtil $bcMath)
    {
    }

    // ---------------------------------------------------------------
    // Delta computation
    // ---------------------------------------------------------------

    public function computeDepositDelta(string $amount, $profit): array
    {
        return [
            'balance'        => $this->bcMath->positiveOf($amount ?: '0.00'),
            'profit'         => $this->bcMath->positiveOf($profit ?: '0.00'),
            'frozen_balance' => '0.00',
        ];
    }

    public function computeDepositDeductFrozenDelta(string $amount, string $walletFrozenBalance): array
    {
        if ($this->bcMath->gte($walletFrozenBalance, $amount)) {
            return [
                'balance'        => '0.00',
                'profit'         => '0.00',
                'frozen_balance' => $this->bcMath->negativeOf($amount),
            ];
        }

        return [
            'balance'        => $this->bcMath->sub($amount, $walletFrozenBalance),
            'profit'         => '0.00',
            'frozen_balance' => $this->bcMath->negativeOf($walletFrozenBalance),
        ];
    }

    public function computeDepositRollbackDelta(string $amount, $profit, $frozenBalance = 0): array
    {
        return [
            'balance'        => $frozenBalance == 0 ? $this->bcMath->negativeOf($amount ?: '0.00') : 0,
            'profit'         => $this->bcMath->negativeOf($profit ?: '0.00'),
            'frozen_balance' => $frozenBalance,
        ];
    }

    public function computeWithdrawDelta(string $amount, string $withdrawType = 'balance'): array
    {
        return [
            'balance'        => ($withdrawType == 'balance') ? $this->bcMath->negativeOf($amount) : '0.00',
            'profit'         => ($withdrawType == 'profit') ? $this->bcMath->negativeOf($amount) : '0.00',
            'frozen_balance' => '0.00',
        ];
    }

    public function computeWithdrawRollbackDelta(string $amount, string $withdrawType = 'balance'): array
    {
        return [
            'balance'        => ($withdrawType == 'balance') ? $this->bcMath->positiveOf($amount) : '0.00',
            'profit'         => ($withdrawType == 'profit') ? $this->bcMath->positiveOf($amount) : '0.00',
            'frozen_balance' => '0.00',
        ];
    }

    public function computeTransferFromDelta(string $amount): array
    {
        return [
            'balance'        => $this->bcMath->negativeOf($amount),
            'profit'         => '0.00',
            'frozen_balance' => '0.00',
        ];
    }

    public function computeTransferToDelta(string $amount): array
    {
        return [
            'balance'        => $this->bcMath->positiveOf($amount),
            'frozen_balance' => '0.00',
        ];
    }

    public function computeRewardDelta(string $reward): array
    {
        return [
            'balance'        => '0.00',
            'profit'         => $this->bcMath->positiveOf($reward),
            'frozen_balance' => '0.00',
        ];
    }

    // ---------------------------------------------------------------
    // Result computation
    // ---------------------------------------------------------------

    public function computeResult(array $walletSnapshot, array $delta): array
    {
        $result = [];

        foreach ($delta as $key => $value) {
            if (isset($walletSnapshot[$key])) {
                $result[$key] = $this->bcMath->add($walletSnapshot[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    // ---------------------------------------------------------------
    // Reward amount computation
    // ---------------------------------------------------------------

    public function computeRewardAmount(string $txAmount, string $rewardAmount, int $rewardUnit): string
    {
        return match ($rewardUnit) {
            1 => $rewardAmount,  // REWARD_UNIT_SINGLE
            2 => $this->bcMath->mulPercent($txAmount, $rewardAmount),  // REWARD_UNIT_PERCENT
            default => throw new \InvalidArgumentException("Unknown reward unit: {$rewardUnit}"),
        };
    }

    // ---------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------

    public function validateAvailableBalance(string $availableBalance, string $balanceDelta): void
    {
        throw_if(
            $this->bcMath->ltZero($balanceDelta)
                && $this->bcMath->lt($availableBalance, $this->bcMath->positiveOf($balanceDelta)),
            new InsufficientAvailableBalance()
        );
    }

    public function validateProfit(string $currentProfit, string $profitDelta): void
    {
        throw_if(
            $this->bcMath->ltZero($profitDelta)
                && $this->bcMath->lt($currentProfit, $this->bcMath->positiveOf($profitDelta)),
            new InsufficientProfit()
        );
    }

    public function validateConflictAwareUpdate(array $walletSnapshot, array $delta): void
    {
        if ($this->bcMath->ltZero($delta['balance'])) {
            throw_if(
                $this->bcMath->lt($walletSnapshot['balance'], $this->bcMath->positiveOf($delta['balance'])),
                new InsufficientBalance()
            );
        }

        throw_if(
            $this->bcMath->ltZero($delta['profit'])
                && $this->bcMath->lt($walletSnapshot['profit'], $this->bcMath->positiveOf($delta['profit'])),
            new InsufficientProfit()
        );

        throw_if(
            $this->bcMath->ltZero($delta['frozen_balance'])
                && $this->bcMath->lt($walletSnapshot['frozen_balance'], $this->bcMath->positiveOf($delta['frozen_balance'])),
            new InsufficientAvailableBalance()
        );
    }

    public function validateDepositRollback(string $availableBalance, string $currentProfit, array $delta): void
    {
        throw_if(
            isset($delta['balance'])
                && $this->bcMath->ltZero((string) $delta['balance'])
                && $this->bcMath->lt($availableBalance, $this->bcMath->positiveOf((string) $delta['balance'])),
            new InsufficientAvailableBalance()
        );

        throw_if(
            isset($delta['profit'])
                && $this->bcMath->ltZero($delta['profit'])
                && $this->bcMath->lt($currentProfit, $this->bcMath->positiveOf($delta['profit'])),
            new InsufficientProfit()
        );
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    public function formatMatchingDepositRewardNote(string $min, string $max, string $reward, int $unit): string
    {
        return implode(' ', [
            '快充奖励',
            $min, '~', $max,
            ($unit === 1) ? "{$reward}/笔" : '',  // REWARD_UNIT_SINGLE
            ($unit === 2) ? "{$reward}%" : '',      // REWARD_UNIT_PERCENT
        ]);
    }

    public function determineDepositHistoryType(string $balanceDelta): int
    {
        // WalletHistory::TYPE_DEPOSIT = 3, TYPE_DEPOSIT_PROFIT = 9
        return $balanceDelta != '0.00' ? 3 : 9;
    }

    public function determineWithdrawHistoryType(string $transactionType): int
    {
        // WalletHistory::TYPE_WITHHOLD = 4, TYPE_WITHDRAW = 12
        return ($transactionType == 'transaction') ? 4 : 12;
    }

    public function determineWithdrawRollbackHistoryType(string $transactionType): int
    {
        // WalletHistory::TYPE_WITHHOLD_ROLLBACK = 5, TYPE_WITHDRAW_ROLLBACK = 13
        return ($transactionType == 'transaction') ? 5 : 13;
    }
}
