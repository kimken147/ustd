<?php

namespace Tests\Unit\Utils;

use App\Utils\BCMathUtil;
use App\Utils\InsufficientAvailableBalance;
use App\Utils\InsufficientBalance;
use App\Utils\InsufficientProfit;
use App\Utils\WalletBalanceCalculator;
use Tests\TestCase;

class WalletBalanceCalculatorTest extends TestCase
{
    private WalletBalanceCalculator $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new WalletBalanceCalculator(new BCMathUtil());
    }

    // ---------------------------------------------------------------
    // computeDepositDelta
    // ---------------------------------------------------------------

    public function test_deposit_delta_with_amount_and_profit(): void
    {
        $delta = $this->calc->computeDepositDelta('100.00', '10.00');

        $this->assertSame('100.00', $delta['balance']);
        $this->assertSame('10.00', $delta['profit']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    public function test_deposit_delta_with_zero_profit(): void
    {
        $delta = $this->calc->computeDepositDelta('50.00', '0');

        $this->assertSame('50.00', $delta['balance']);
        $this->assertSame('0.00', $delta['profit']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    public function test_deposit_delta_with_empty_string_values(): void
    {
        $delta = $this->calc->computeDepositDelta('', '');

        $this->assertSame('0.00', $delta['balance']);
        $this->assertSame('0.00', $delta['profit']);
    }

    // ---------------------------------------------------------------
    // computeDepositDeductFrozenDelta
    // ---------------------------------------------------------------

    public function test_deposit_deduct_frozen_when_frozen_covers_amount(): void
    {
        $delta = $this->calc->computeDepositDeductFrozenDelta('100.00', '200.00');

        $this->assertSame('0.00', $delta['balance']);
        $this->assertSame('0.00', $delta['profit']);
        $this->assertSame('-100.00', $delta['frozen_balance']);
    }

    public function test_deposit_deduct_frozen_when_frozen_equals_amount(): void
    {
        $delta = $this->calc->computeDepositDeductFrozenDelta('100.00', '100.00');

        $this->assertSame('0.00', $delta['balance']);
        $this->assertSame('-100.00', $delta['frozen_balance']);
    }

    public function test_deposit_deduct_frozen_when_frozen_less_than_amount(): void
    {
        $delta = $this->calc->computeDepositDeductFrozenDelta('100.00', '30.00');

        $this->assertSame('70.00', $delta['balance']);
        $this->assertSame('0.00', $delta['profit']);
        $this->assertSame('-30.00', $delta['frozen_balance']);
    }

    // ---------------------------------------------------------------
    // computeDepositRollbackDelta
    // ---------------------------------------------------------------

    public function test_deposit_rollback_delta_no_frozen_balance(): void
    {
        $delta = $this->calc->computeDepositRollbackDelta('100.00', '10.00');

        $this->assertSame('-100.00', $delta['balance']);
        $this->assertSame('-10.00', $delta['profit']);
        $this->assertSame(0, $delta['frozen_balance']);
    }

    public function test_deposit_rollback_delta_with_frozen_balance(): void
    {
        $delta = $this->calc->computeDepositRollbackDelta('100.00', '10.00', '50.00');

        $this->assertSame(0, $delta['balance']);
        $this->assertSame('-10.00', $delta['profit']);
        $this->assertSame('50.00', $delta['frozen_balance']);
    }

    public function test_deposit_rollback_delta_zero_amount(): void
    {
        $delta = $this->calc->computeDepositRollbackDelta('0', '5.00');

        $this->assertSame('0.00', $delta['balance']);
        $this->assertSame('-5.00', $delta['profit']);
    }

    // ---------------------------------------------------------------
    // computeWithdrawDelta
    // ---------------------------------------------------------------

    public function test_withdraw_delta_from_balance(): void
    {
        $delta = $this->calc->computeWithdrawDelta('100.00', 'balance');

        $this->assertSame('-100.00', $delta['balance']);
        $this->assertSame('0.00', $delta['profit']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    public function test_withdraw_delta_from_profit(): void
    {
        $delta = $this->calc->computeWithdrawDelta('100.00', 'profit');

        $this->assertSame('0.00', $delta['balance']);
        $this->assertSame('-100.00', $delta['profit']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    public function test_withdraw_delta_defaults_to_balance(): void
    {
        $delta = $this->calc->computeWithdrawDelta('50.00');

        $this->assertSame('-50.00', $delta['balance']);
        $this->assertSame('0.00', $delta['profit']);
    }

    // ---------------------------------------------------------------
    // computeWithdrawRollbackDelta
    // ---------------------------------------------------------------

    public function test_withdraw_rollback_delta_from_balance(): void
    {
        $delta = $this->calc->computeWithdrawRollbackDelta('100.00', 'balance');

        $this->assertSame('100.00', $delta['balance']);
        $this->assertSame('0.00', $delta['profit']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    public function test_withdraw_rollback_delta_from_profit(): void
    {
        $delta = $this->calc->computeWithdrawRollbackDelta('100.00', 'profit');

        $this->assertSame('0.00', $delta['balance']);
        $this->assertSame('100.00', $delta['profit']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    // ---------------------------------------------------------------
    // computeTransferFromDelta / computeTransferToDelta
    // ---------------------------------------------------------------

    public function test_transfer_from_delta(): void
    {
        $delta = $this->calc->computeTransferFromDelta('200.00');

        $this->assertSame('-200.00', $delta['balance']);
        $this->assertSame('0.00', $delta['profit']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    public function test_transfer_to_delta(): void
    {
        $delta = $this->calc->computeTransferToDelta('200.00');

        $this->assertSame('200.00', $delta['balance']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    // ---------------------------------------------------------------
    // computeRewardDelta
    // ---------------------------------------------------------------

    public function test_reward_delta(): void
    {
        $delta = $this->calc->computeRewardDelta('15.50');

        $this->assertSame('0.00', $delta['balance']);
        $this->assertSame('15.50', $delta['profit']);
        $this->assertSame('0.00', $delta['frozen_balance']);
    }

    // ---------------------------------------------------------------
    // computeResult
    // ---------------------------------------------------------------

    public function test_compute_result_with_positive_delta(): void
    {
        $snapshot = ['balance' => '100.00', 'profit' => '50.00', 'frozen_balance' => '0.00'];
        $delta = ['balance' => '20.00', 'profit' => '5.00', 'frozen_balance' => '0.00'];

        $result = $this->calc->computeResult($snapshot, $delta);

        $this->assertSame('120.00', $result['balance']);
        $this->assertSame('55.00', $result['profit']);
        $this->assertSame('0.00', $result['frozen_balance']);
    }

    public function test_compute_result_with_negative_delta(): void
    {
        $snapshot = ['balance' => '100.00', 'profit' => '50.00', 'frozen_balance' => '10.00'];
        $delta = ['balance' => '-30.00', 'profit' => '-10.00', 'frozen_balance' => '0.00'];

        $result = $this->calc->computeResult($snapshot, $delta);

        $this->assertSame('70.00', $result['balance']);
        $this->assertSame('40.00', $result['profit']);
        $this->assertSame('10.00', $result['frozen_balance']);
    }

    public function test_compute_result_with_mixed_delta(): void
    {
        $snapshot = ['balance' => '100.00', 'profit' => '50.00', 'frozen_balance' => '20.00'];
        $delta = ['balance' => '10.00', 'profit' => '-5.00', 'frozen_balance' => '-20.00'];

        $result = $this->calc->computeResult($snapshot, $delta);

        $this->assertSame('110.00', $result['balance']);
        $this->assertSame('45.00', $result['profit']);
        $this->assertSame('0.00', $result['frozen_balance']);
    }

    public function test_compute_result_with_zero_delta(): void
    {
        $snapshot = ['balance' => '100.00', 'profit' => '50.00', 'frozen_balance' => '0.00'];
        $delta = ['balance' => '0.00', 'profit' => '0.00', 'frozen_balance' => '0.00'];

        $result = $this->calc->computeResult($snapshot, $delta);

        $this->assertSame('100.00', $result['balance']);
        $this->assertSame('50.00', $result['profit']);
        $this->assertSame('0.00', $result['frozen_balance']);
    }

    public function test_compute_result_with_partial_delta(): void
    {
        $snapshot = ['balance' => '100.00', 'profit' => '50.00', 'frozen_balance' => '0.00'];
        $delta = ['balance' => '20.00', 'frozen_balance' => '0.00'];

        $result = $this->calc->computeResult($snapshot, $delta);

        $this->assertSame('120.00', $result['balance']);
        $this->assertArrayNotHasKey('profit', $result);
        $this->assertSame('0.00', $result['frozen_balance']);
    }

    // ---------------------------------------------------------------
    // computeRewardAmount
    // ---------------------------------------------------------------

    public function test_reward_amount_single_unit(): void
    {
        $reward = $this->calc->computeRewardAmount('1000.00', '5.00', 1);

        $this->assertSame('5.00', $reward);
    }

    public function test_reward_amount_percent_unit(): void
    {
        $reward = $this->calc->computeRewardAmount('1000.00', '10.00', 2);

        $this->assertSame('100.00', $reward);
    }

    public function test_reward_amount_unknown_unit_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calc->computeRewardAmount('1000.00', '5.00', 99);
    }

    // ---------------------------------------------------------------
    // validateAvailableBalance
    // ---------------------------------------------------------------

    public function test_validate_available_balance_passes_when_sufficient(): void
    {
        $this->calc->validateAvailableBalance('100.00', '-50.00');
        $this->assertTrue(true); // no exception
    }

    public function test_validate_available_balance_passes_when_positive_delta(): void
    {
        $this->calc->validateAvailableBalance('10.00', '50.00');
        $this->assertTrue(true);
    }

    public function test_validate_available_balance_fails_when_insufficient(): void
    {
        $this->expectException(InsufficientAvailableBalance::class);

        $this->calc->validateAvailableBalance('30.00', '-50.00');
    }

    public function test_validate_available_balance_passes_when_exactly_equal(): void
    {
        $this->calc->validateAvailableBalance('50.00', '-50.00');
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // validateProfit
    // ---------------------------------------------------------------

    public function test_validate_profit_passes_when_sufficient(): void
    {
        $this->calc->validateProfit('100.00', '-50.00');
        $this->assertTrue(true);
    }

    public function test_validate_profit_fails_when_insufficient(): void
    {
        $this->expectException(InsufficientProfit::class);

        $this->calc->validateProfit('30.00', '-50.00');
    }

    public function test_validate_profit_passes_when_positive_delta(): void
    {
        $this->calc->validateProfit('0.00', '50.00');
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // validateConflictAwareUpdate
    // ---------------------------------------------------------------

    public function test_conflict_aware_passes_when_sufficient(): void
    {
        $snapshot = ['balance' => '100.00', 'profit' => '50.00', 'frozen_balance' => '20.00'];
        $delta = ['balance' => '-50.00', 'profit' => '-10.00', 'frozen_balance' => '-5.00'];

        $this->calc->validateConflictAwareUpdate($snapshot, $delta);
        $this->assertTrue(true);
    }

    public function test_conflict_aware_fails_insufficient_balance(): void
    {
        $snapshot = ['balance' => '30.00', 'profit' => '50.00', 'frozen_balance' => '20.00'];
        $delta = ['balance' => '-50.00', 'profit' => '0.00', 'frozen_balance' => '0.00'];

        $this->expectException(InsufficientBalance::class);
        $this->calc->validateConflictAwareUpdate($snapshot, $delta);
    }

    public function test_conflict_aware_fails_insufficient_profit(): void
    {
        $snapshot = ['balance' => '100.00', 'profit' => '5.00', 'frozen_balance' => '20.00'];
        $delta = ['balance' => '0.00', 'profit' => '-10.00', 'frozen_balance' => '0.00'];

        $this->expectException(InsufficientProfit::class);
        $this->calc->validateConflictAwareUpdate($snapshot, $delta);
    }

    public function test_conflict_aware_fails_insufficient_frozen_balance(): void
    {
        $snapshot = ['balance' => '100.00', 'profit' => '50.00', 'frozen_balance' => '5.00'];
        $delta = ['balance' => '0.00', 'profit' => '0.00', 'frozen_balance' => '-10.00'];

        $this->expectException(InsufficientAvailableBalance::class);
        $this->calc->validateConflictAwareUpdate($snapshot, $delta);
    }

    // ---------------------------------------------------------------
    // validateDepositRollback (bug fix verification)
    // ---------------------------------------------------------------

    public function test_deposit_rollback_validation_passes_when_sufficient(): void
    {
        $delta = ['balance' => '-50.00', 'profit' => '-10.00', 'frozen_balance' => 0];

        $this->calc->validateDepositRollback('100.00', '50.00', $delta);
        $this->assertTrue(true);
    }

    public function test_deposit_rollback_validation_fails_insufficient_balance(): void
    {
        $delta = ['balance' => '-100.00', 'profit' => '-5.00', 'frozen_balance' => 0];

        $this->expectException(InsufficientAvailableBalance::class);
        $this->calc->validateDepositRollback('50.00', '50.00', $delta);
    }

    public function test_deposit_rollback_validation_fails_insufficient_profit(): void
    {
        $delta = ['balance' => '-10.00', 'profit' => '-50.00', 'frozen_balance' => 0];

        $this->expectException(InsufficientProfit::class);
        $this->calc->validateDepositRollback('100.00', '20.00', $delta);
    }

    public function test_deposit_rollback_validation_skips_when_balance_zero(): void
    {
        // When frozen_balance != 0, balance delta is 0 (not negative), so balance check should pass
        $delta = ['balance' => 0, 'profit' => '-5.00', 'frozen_balance' => '50.00'];

        $this->calc->validateDepositRollback('10.00', '50.00', $delta);
        $this->assertTrue(true);
    }

    // ---------------------------------------------------------------
    // formatMatchingDepositRewardNote
    // ---------------------------------------------------------------

    public function test_format_matching_deposit_reward_note_single(): void
    {
        $note = $this->calc->formatMatchingDepositRewardNote('100.00', '500.00', '5.00', 1);

        $this->assertStringContainsString('快充奖励', $note);
        $this->assertStringContainsString('100.00', $note);
        $this->assertStringContainsString('500.00', $note);
        $this->assertStringContainsString('5.00/笔', $note);
    }

    public function test_format_matching_deposit_reward_note_percent(): void
    {
        $note = $this->calc->formatMatchingDepositRewardNote('100.00', '500.00', '10.00', 2);

        $this->assertStringContainsString('快充奖励', $note);
        $this->assertStringContainsString('10.00%', $note);
    }

    // ---------------------------------------------------------------
    // determineDepositHistoryType
    // ---------------------------------------------------------------

    public function test_deposit_history_type_with_balance(): void
    {
        $this->assertSame(3, $this->calc->determineDepositHistoryType('50.00'));
    }

    public function test_deposit_history_type_profit_only(): void
    {
        $this->assertSame(9, $this->calc->determineDepositHistoryType('0.00'));
    }

    // ---------------------------------------------------------------
    // determineWithdrawHistoryType
    // ---------------------------------------------------------------

    public function test_withdraw_history_type_transaction(): void
    {
        $this->assertSame(4, $this->calc->determineWithdrawHistoryType('transaction'));
    }

    public function test_withdraw_history_type_withdraw(): void
    {
        $this->assertSame(12, $this->calc->determineWithdrawHistoryType('withdraw'));
    }

    // ---------------------------------------------------------------
    // determineWithdrawRollbackHistoryType
    // ---------------------------------------------------------------

    public function test_withdraw_rollback_history_type_transaction(): void
    {
        $this->assertSame(5, $this->calc->determineWithdrawRollbackHistoryType('transaction'));
    }

    public function test_withdraw_rollback_history_type_withdraw(): void
    {
        $this->assertSame(13, $this->calc->determineWithdrawRollbackHistoryType('withdraw'));
    }
}
