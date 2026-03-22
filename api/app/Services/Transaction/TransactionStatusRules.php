<?php

namespace App\Services\Transaction;

use App\Models\Channel;
use App\Models\Transaction;

class TransactionStatusRules
{
    // --- 1. Status transition guards ---

    public static function canTransitionTo(int $currentStatus, string $transition, bool $fromPayingTimedOut = false): bool
    {
        $allowed = match ($transition) {
            'success' => self::allowedStatusesForSuccess($fromPayingTimedOut),
            'failed' => self::allowedStatusesForFailed(),
            'paufen_withdraw' => self::allowedStatusesForPaufenWithdraw(),
            'third_channel_withdraw' => self::allowedStatusesForThirdChannelWithdraw(),
            'normal_withdraw' => self::allowedStatusesForNormalWithdraw(),
            'received' => self::allowedStatusesForReceived(),
            default => [],
        };

        return in_array($currentStatus, $allowed);
    }

    public static function allowedStatusesForSuccess(bool $fromPayingTimedOut = false): array
    {
        return $fromPayingTimedOut
            ? [Transaction::STATUS_PAYING_TIMED_OUT]
            : [Transaction::STATUS_MATCHING, Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED, Transaction::STATUS_THIRD_PAYING];
    }

    public static function allowedStatusesForFailed(): array
    {
        return [
            Transaction::STATUS_PENDING_REVIEW,
            Transaction::STATUS_PAYING,
            Transaction::STATUS_PAYING_TIMED_OUT,
            Transaction::STATUS_RECEIVED,
            Transaction::STATUS_THIRD_PAYING,
            Transaction::STATUS_MATCHING,
            Transaction::STATUS_SUCCESS,
            Transaction::STATUS_MANUAL_SUCCESS,
        ];
    }

    public static function alreadyFailedStatuses(): array
    {
        return [Transaction::STATUS_FAILED];
    }

    public static function allowedStatusesForPaufenWithdraw(): array
    {
        return [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_MATCHING, Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED];
    }

    public static function allowedStatusesForThirdChannelWithdraw(): array
    {
        return [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING];
    }

    public static function allowedStatusesForNormalWithdraw(): array
    {
        return [Transaction::STATUS_PENDING_REVIEW, Transaction::STATUS_PAYING, Transaction::STATUS_RECEIVED];
    }

    public static function allowedStatusesForReceived(): array
    {
        return [Transaction::STATUS_PAYING, Transaction::STATUS_THIRD_PAYING];
    }

    // --- 2. Allowed types for withdraw type changes ---

    public static function allowedTypesForWithdrawTypeChange(): array
    {
        return [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW];
    }

    public static function allowedTypesForThirdChannelWithdraw(): array
    {
        return [Transaction::TYPE_NORMAL_WITHDRAW];
    }

    public static function allowedTypesForReceived(): array
    {
        return [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW];
    }

    // --- 3. Lock ownership validation ---

    /**
     * @return array{valid: bool, error: ?string}
     */
    public static function validateWithdrawLockOwnership(
        bool $shouldLock,
        bool $isLocked,
        ?int $lockedById,
        ?int $authUserId,
        bool $isAdmin
    ): array {
        if (!$shouldLock) {
            return ['valid' => true, 'error' => null];
        }

        if (!$isLocked) {
            return ['valid' => false, 'error' => '请先锁定'];
        }

        if ($lockedById != $authUserId && !$isAdmin) {
            return ['valid' => false, 'error' => '非锁定人无法操作'];
        }

        return ['valid' => true, 'error' => null];
    }

    // --- 4. Notify status ---

    public static function determineNotifyStatus(?string $notifyUrl): int
    {
        return $notifyUrl ? Transaction::NOTIFY_STATUS_PENDING : Transaction::NOTIFY_STATUS_NONE;
    }

    // --- 5. Fee actualization ---

    /**
     * @param  array<array{fee: string, profit: string}>  $transactionFees
     * @return array<array{actual_fee: string, actual_profit: string}>
     */
    public static function actualizeFeesForSuccess(array $transactionFees): array
    {
        return array_map(fn ($fee) => [
            'actual_fee' => $fee['fee'],
            'actual_profit' => $fee['profit'],
        ], $transactionFees);
    }

    /**
     * @param  array<array{fee: string, profit: string}>  $transactionFees
     * @return array<array{actual_fee: string, actual_profit: string}>
     */
    public static function actualizeFeesForFailure(array $transactionFees): array
    {
        return array_map(fn ($fee) => [
            'actual_fee' => '0',
            'actual_profit' => '0',
        ], $transactionFees);
    }

    // --- 6. Paufen refund on failure ---

    public static function needsPaufenRefundOnFailure(bool $hasFrom, bool $refundYet, bool $cancelPaufen): bool
    {
        return $hasFrom && $refundYet && !$cancelPaufen;
    }

    // --- 7. Paufen redeposition on success ---

    public static function needsPaufenRedepositionOnSuccess(
        int $type,
        bool $fromPayingTimedOut,
        bool $refundYet,
        bool $cancelPaufen
    ): bool {
        return $type === Transaction::TYPE_PAUFEN_TRANSACTION
            && $fromPayingTimedOut
            && !$refundYet
            && !$cancelPaufen;
    }

    // --- 8. All children reached status ---

    public static function allChildrenReachedStatus(int $matchingCount, int $totalCount): bool
    {
        return $totalCount > 0 && $matchingCount === $totalCount;
    }

    // --- 9. USDT withdraw dispatch ---

    public static function shouldDispatchUsdtWithdraw(
        bool $keepLock,
        ?string $channelCode,
        ?int $thirdchannelId,
        ?int $toChannelAccountId
    ): bool {
        return !$keepLock
            && in_array($channelCode, Channel::USDT_CODES, true)
            && !$thirdchannelId
            && (bool) $toChannelAccountId;
    }
}
