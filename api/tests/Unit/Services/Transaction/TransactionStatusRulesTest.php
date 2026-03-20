<?php

namespace Tests\Unit\Services\Transaction;

use App\Models\Channel;
use App\Models\Transaction;
use App\Services\Transaction\TransactionStatusRules;
use Tests\TestCase;

class TransactionStatusRulesTest extends TestCase
{
    // --- canTransitionTo: success ---

    public function test_canTransitionTo_success_from_matching(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_MATCHING, 'success'));
    }

    public function test_canTransitionTo_success_from_paying(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING, 'success'));
    }

    public function test_canTransitionTo_success_from_received(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_RECEIVED, 'success'));
    }

    public function test_canTransitionTo_success_from_third_paying(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_THIRD_PAYING, 'success'));
    }

    public function test_canTransitionTo_success_from_paying_timed_out_without_flag(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING_TIMED_OUT, 'success'));
    }

    public function test_canTransitionTo_success_from_paying_timed_out_with_flag(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING_TIMED_OUT, 'success', true));
    }

    public function test_canTransitionTo_success_from_paying_with_timed_out_flag_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING, 'success', true));
    }

    public function test_canTransitionTo_success_from_failed_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_FAILED, 'success'));
    }

    public function test_canTransitionTo_success_from_pending_review_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PENDING_REVIEW, 'success'));
    }

    // --- canTransitionTo: failed ---

    public function test_canTransitionTo_failed_from_pending_review(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PENDING_REVIEW, 'failed'));
    }

    public function test_canTransitionTo_failed_from_paying(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING, 'failed'));
    }

    public function test_canTransitionTo_failed_from_paying_timed_out(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING_TIMED_OUT, 'failed'));
    }

    public function test_canTransitionTo_failed_from_success(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_SUCCESS, 'failed'));
    }

    public function test_canTransitionTo_failed_from_manual_success(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_MANUAL_SUCCESS, 'failed'));
    }

    public function test_canTransitionTo_failed_from_already_failed_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_FAILED, 'failed'));
    }

    // --- canTransitionTo: paufen_withdraw ---

    public function test_canTransitionTo_paufen_withdraw_from_pending_review(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PENDING_REVIEW, 'paufen_withdraw'));
    }

    public function test_canTransitionTo_paufen_withdraw_from_paying(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING, 'paufen_withdraw'));
    }

    public function test_canTransitionTo_paufen_withdraw_from_received(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_RECEIVED, 'paufen_withdraw'));
    }

    public function test_canTransitionTo_paufen_withdraw_from_success_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_SUCCESS, 'paufen_withdraw'));
    }

    // --- canTransitionTo: third_channel_withdraw ---

    public function test_canTransitionTo_third_channel_withdraw_from_pending_review(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PENDING_REVIEW, 'third_channel_withdraw'));
    }

    public function test_canTransitionTo_third_channel_withdraw_from_paying(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING, 'third_channel_withdraw'));
    }

    public function test_canTransitionTo_third_channel_withdraw_from_received_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_RECEIVED, 'third_channel_withdraw'));
    }

    // --- canTransitionTo: normal_withdraw ---

    public function test_canTransitionTo_normal_withdraw_from_pending_review(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PENDING_REVIEW, 'normal_withdraw'));
    }

    public function test_canTransitionTo_normal_withdraw_from_received(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_RECEIVED, 'normal_withdraw'));
    }

    public function test_canTransitionTo_normal_withdraw_from_success_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_SUCCESS, 'normal_withdraw'));
    }

    // --- canTransitionTo: received ---

    public function test_canTransitionTo_received_from_paying(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING, 'received'));
    }

    public function test_canTransitionTo_received_from_third_paying(): void
    {
        $this->assertTrue(TransactionStatusRules::canTransitionTo(Transaction::STATUS_THIRD_PAYING, 'received'));
    }

    public function test_canTransitionTo_received_from_pending_review_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PENDING_REVIEW, 'received'));
    }

    // --- canTransitionTo: unknown transition ---

    public function test_canTransitionTo_unknown_transition_rejects(): void
    {
        $this->assertFalse(TransactionStatusRules::canTransitionTo(Transaction::STATUS_PAYING, 'nonexistent'));
    }

    // --- alreadyFailedStatuses ---

    public function test_alreadyFailedStatuses_contains_failed(): void
    {
        $this->assertContains(Transaction::STATUS_FAILED, TransactionStatusRules::alreadyFailedStatuses());
    }

    // --- allowedTypesForWithdrawTypeChange ---

    public function test_allowedTypesForWithdrawTypeChange(): void
    {
        $types = TransactionStatusRules::allowedTypesForWithdrawTypeChange();
        $this->assertContains(Transaction::TYPE_PAUFEN_WITHDRAW, $types);
        $this->assertContains(Transaction::TYPE_NORMAL_WITHDRAW, $types);
        $this->assertNotContains(Transaction::TYPE_PAUFEN_TRANSACTION, $types);
    }

    // --- allowedTypesForThirdChannelWithdraw ---

    public function test_allowedTypesForThirdChannelWithdraw(): void
    {
        $types = TransactionStatusRules::allowedTypesForThirdChannelWithdraw();
        $this->assertContains(Transaction::TYPE_NORMAL_WITHDRAW, $types);
        $this->assertNotContains(Transaction::TYPE_PAUFEN_WITHDRAW, $types);
    }

    // --- allowedTypesForReceived ---

    public function test_allowedTypesForReceived(): void
    {
        $types = TransactionStatusRules::allowedTypesForReceived();
        $this->assertContains(Transaction::TYPE_PAUFEN_WITHDRAW, $types);
        $this->assertContains(Transaction::TYPE_NORMAL_WITHDRAW, $types);
    }

    // --- validateWithdrawLockOwnership ---

    public function test_validateLock_shouldLock_false_always_valid(): void
    {
        $result = TransactionStatusRules::validateWithdrawLockOwnership(false, false, null, 1, false);
        $this->assertTrue($result['valid']);
        $this->assertNull($result['error']);
    }

    public function test_validateLock_not_locked_returns_error(): void
    {
        $result = TransactionStatusRules::validateWithdrawLockOwnership(true, false, null, 1, false);
        $this->assertFalse($result['valid']);
        $this->assertEquals('请先锁定', $result['error']);
    }

    public function test_validateLock_locked_by_same_user_valid(): void
    {
        $result = TransactionStatusRules::validateWithdrawLockOwnership(true, true, 5, 5, false);
        $this->assertTrue($result['valid']);
    }

    public function test_validateLock_locked_by_different_user_admin_valid(): void
    {
        $result = TransactionStatusRules::validateWithdrawLockOwnership(true, true, 5, 99, true);
        $this->assertTrue($result['valid']);
    }

    public function test_validateLock_locked_by_different_user_non_admin_invalid(): void
    {
        $result = TransactionStatusRules::validateWithdrawLockOwnership(true, true, 5, 99, false);
        $this->assertFalse($result['valid']);
        $this->assertEquals('非锁定人无法操作', $result['error']);
    }

    // --- determineNotifyStatus ---

    public function test_determineNotifyStatus_with_url(): void
    {
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_PENDING,
            TransactionStatusRules::determineNotifyStatus('https://example.com/callback')
        );
    }

    public function test_determineNotifyStatus_without_url(): void
    {
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_NONE,
            TransactionStatusRules::determineNotifyStatus(null)
        );
    }

    public function test_determineNotifyStatus_with_empty_string(): void
    {
        // empty string is falsy in PHP
        $this->assertEquals(
            Transaction::NOTIFY_STATUS_NONE,
            TransactionStatusRules::determineNotifyStatus('')
        );
    }

    // --- actualizeFeesForSuccess ---

    public function test_actualizeFeesForSuccess_maps_fees(): void
    {
        $fees = [
            ['fee' => '10.00', 'profit' => '5.00'],
            ['fee' => '20.00', 'profit' => '8.00'],
        ];

        $result = TransactionStatusRules::actualizeFeesForSuccess($fees);

        $this->assertEquals([
            ['actual_fee' => '10.00', 'actual_profit' => '5.00'],
            ['actual_fee' => '20.00', 'actual_profit' => '8.00'],
        ], $result);
    }

    public function test_actualizeFeesForSuccess_empty_array(): void
    {
        $this->assertEquals([], TransactionStatusRules::actualizeFeesForSuccess([]));
    }

    // --- actualizeFeesForFailure ---

    public function test_actualizeFeesForFailure_zeros_all(): void
    {
        $fees = [
            ['fee' => '10.00', 'profit' => '5.00'],
            ['fee' => '20.00', 'profit' => '8.00'],
        ];

        $result = TransactionStatusRules::actualizeFeesForFailure($fees);

        $this->assertEquals([
            ['actual_fee' => '0', 'actual_profit' => '0'],
            ['actual_fee' => '0', 'actual_profit' => '0'],
        ], $result);
    }

    public function test_actualizeFeesForFailure_empty_array(): void
    {
        $this->assertEquals([], TransactionStatusRules::actualizeFeesForFailure([]));
    }

    // --- needsPaufenRefundOnFailure ---

    public function test_needsPaufenRefundOnFailure_all_true(): void
    {
        $this->assertTrue(TransactionStatusRules::needsPaufenRefundOnFailure(true, true, false));
    }

    public function test_needsPaufenRefundOnFailure_no_from(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRefundOnFailure(false, true, false));
    }

    public function test_needsPaufenRefundOnFailure_not_refund_yet(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRefundOnFailure(true, false, false));
    }

    public function test_needsPaufenRefundOnFailure_cancel_paufen(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRefundOnFailure(true, true, true));
    }

    public function test_needsPaufenRefundOnFailure_all_false(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRefundOnFailure(false, false, false));
    }

    public function test_needsPaufenRefundOnFailure_no_from_cancel_paufen(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRefundOnFailure(false, true, true));
    }

    public function test_needsPaufenRefundOnFailure_not_refund_yet_cancel_paufen(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRefundOnFailure(true, false, true));
    }

    public function test_needsPaufenRefundOnFailure_all_true_cancel_paufen(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRefundOnFailure(false, false, true));
    }

    // --- needsPaufenRedepositionOnSuccess ---

    public function test_needsPaufenRedepositionOnSuccess_all_conditions_met(): void
    {
        $this->assertTrue(TransactionStatusRules::needsPaufenRedepositionOnSuccess(
            Transaction::TYPE_PAUFEN_TRANSACTION, true, false, false
        ));
    }

    public function test_needsPaufenRedepositionOnSuccess_wrong_type(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRedepositionOnSuccess(
            Transaction::TYPE_NORMAL_WITHDRAW, true, false, false
        ));
    }

    public function test_needsPaufenRedepositionOnSuccess_not_from_paying_timed_out(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRedepositionOnSuccess(
            Transaction::TYPE_PAUFEN_TRANSACTION, false, false, false
        ));
    }

    public function test_needsPaufenRedepositionOnSuccess_already_refunded(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRedepositionOnSuccess(
            Transaction::TYPE_PAUFEN_TRANSACTION, true, true, false
        ));
    }

    public function test_needsPaufenRedepositionOnSuccess_cancel_paufen(): void
    {
        $this->assertFalse(TransactionStatusRules::needsPaufenRedepositionOnSuccess(
            Transaction::TYPE_PAUFEN_TRANSACTION, true, false, true
        ));
    }

    // --- allChildrenReachedStatus ---

    public function test_allChildrenReachedStatus_all_match(): void
    {
        $this->assertTrue(TransactionStatusRules::allChildrenReachedStatus(3, 3));
    }

    public function test_allChildrenReachedStatus_partial(): void
    {
        $this->assertFalse(TransactionStatusRules::allChildrenReachedStatus(2, 3));
    }

    public function test_allChildrenReachedStatus_zero_children(): void
    {
        $this->assertFalse(TransactionStatusRules::allChildrenReachedStatus(0, 0));
    }

    public function test_allChildrenReachedStatus_none_match(): void
    {
        $this->assertFalse(TransactionStatusRules::allChildrenReachedStatus(0, 3));
    }

    // --- shouldDispatchUsdtWithdraw ---

    public function test_shouldDispatchUsdtWithdraw_all_conditions_met(): void
    {
        $this->assertTrue(TransactionStatusRules::shouldDispatchUsdtWithdraw(
            false, Channel::CODE_USDT_TRC20, null, 1
        ));
    }

    public function test_shouldDispatchUsdtWithdraw_keep_lock(): void
    {
        $this->assertFalse(TransactionStatusRules::shouldDispatchUsdtWithdraw(
            true, Channel::CODE_USDT_TRC20, null, 1
        ));
    }

    public function test_shouldDispatchUsdtWithdraw_wrong_channel(): void
    {
        $this->assertFalse(TransactionStatusRules::shouldDispatchUsdtWithdraw(
            false, 'BANK', null, 1
        ));
    }

    public function test_shouldDispatchUsdtWithdraw_has_thirdchannel(): void
    {
        $this->assertFalse(TransactionStatusRules::shouldDispatchUsdtWithdraw(
            false, Channel::CODE_USDT_TRC20, 5, 1
        ));
    }

    public function test_shouldDispatchUsdtWithdraw_no_from_channel_account(): void
    {
        $this->assertFalse(TransactionStatusRules::shouldDispatchUsdtWithdraw(
            false, Channel::CODE_USDT_TRC20, null, null
        ));
    }
}
