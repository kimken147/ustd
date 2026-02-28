<?php

namespace Tests\Unit\Services\Transaction;

use App\Services\Transaction\TransactionFeeCalculator;
use App\Utils\BCMathUtil;
use Tests\TestCase;

class TransactionFeeCalculatorTest extends TestCase
{
    private TransactionFeeCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new TransactionFeeCalculator(new BCMathUtil());
    }

    // ===== allocateWithdrawFees =====

    public function test_allocateWithdrawFees_single_user(): void
    {
        $result = $this->calculator->allocateWithdrawFees(['10.00']);

        $this->assertCount(1, $result);
        $this->assertEquals('0', $result[0]['profit']);
        $this->assertEquals('10.00', $result[0]['fee']);
    }

    public function test_allocateWithdrawFees_two_users(): void
    {
        // root=5, leaf=10 → root profit = subMinZero(10, 5) = 5; leaf fee = 10
        $result = $this->calculator->allocateWithdrawFees(['5.00', '10.00']);

        $this->assertCount(2, $result);
        $this->assertEquals('5.00', $result[0]['profit']);
        $this->assertEquals('0', $result[0]['fee']);
        $this->assertEquals('0', $result[1]['profit']);
        $this->assertEquals('10.00', $result[1]['fee']);
    }

    public function test_allocateWithdrawFees_three_users(): void
    {
        // root=2, mid=5, leaf=10
        $result = $this->calculator->allocateWithdrawFees(['2.00', '5.00', '10.00']);

        $this->assertCount(3, $result);
        $this->assertEquals('3.00', $result[0]['profit']); // subMinZero(5, 2)
        $this->assertEquals('5.00', $result[1]['profit']); // subMinZero(10, 5)
        $this->assertEquals('0', $result[2]['profit']);
        $this->assertEquals('10.00', $result[2]['fee']);
    }

    public function test_allocateWithdrawFees_inverted_rates_clamp_to_zero(): void
    {
        // child fee < parent fee → profit clamped to 0
        $result = $this->calculator->allocateWithdrawFees(['10.00', '5.00']);

        $this->assertEquals('0.00', $result[0]['profit']); // subMinZero(5, 10) = 0
        $this->assertEquals('5.00', $result[1]['fee']);
    }

    // ===== calculateThirdChannelFee =====

    public function test_calculateThirdChannelFee_percent_plus_flat(): void
    {
        // amount=1000, percent=1.5%, withdrawFee=2.00
        // mulPercent(1000, 1.5) = 1000 * 0.015 = 15.00; sum(15.00, 2.00) = 17.00
        $result = $this->calculator->calculateThirdChannelFee('1000.00', '1.5', '2.00');

        $this->assertEquals('17.00', $result);
    }

    public function test_calculateThirdChannelFee_zero_percent(): void
    {
        $result = $this->calculator->calculateThirdChannelFee('1000.00', '0', '5.00');

        $this->assertEquals('5.00', $result);
    }

    public function test_calculateThirdChannelFee_zero_flat_fee(): void
    {
        $result = $this->calculator->calculateThirdChannelFee('1000.00', '2', '0');

        $this->assertEquals('20.00', $result);
    }

    // ===== calculateWithdrawSystemProfit =====

    public function test_calculateWithdrawSystemProfit_no_third_channel(): void
    {
        $result = $this->calculator->calculateWithdrawSystemProfit('15.00');

        $this->assertEquals('15.00', $result);
    }

    public function test_calculateWithdrawSystemProfit_with_third_channel(): void
    {
        $result = $this->calculator->calculateWithdrawSystemProfit('15.00', '10.00');

        $this->assertEquals('5.00', $result);
    }

    public function test_calculateWithdrawSystemProfit_third_channel_exceeds_top_fee(): void
    {
        $result = $this->calculator->calculateWithdrawSystemProfit('5.00', '10.00');

        $this->assertEquals('0', $result); // clamped to 0, max() returns the 0 literal
    }

    // ===== allocateProviderFees =====

    public function test_allocateProviderFees_single_provider(): void
    {
        // floating=1000, percent=[3]
        // profit = mulPercent(1000, 3) = 30; fee = subMinZero(1000, 30) = 970
        $result = $this->calculator->allocateProviderFees('1000.00', ['3']);

        $this->assertCount(1, $result);
        $this->assertEquals('30.00', $result[0]['profit']);
        $this->assertEquals('970.00', $result[0]['fee']);
    }

    public function test_allocateProviderFees_two_providers(): void
    {
        // floating=1000, percents=[5, 3] (parent=5%, child=3%)
        // agent: profitPercent = subMinZero(5, 3) = 2; profit = mulPercent(1000, 2) = 20
        // leaf: profitPercent = 3; profit = mulPercent(1000, 3) = 30; fee = subMinZero(1000, 30) = 970
        $result = $this->calculator->allocateProviderFees('1000.00', ['5', '3']);

        $this->assertCount(2, $result);
        $this->assertEquals('20.00', $result[0]['profit']);
        $this->assertEquals('0', $result[0]['fee']);
        $this->assertEquals('30.00', $result[1]['profit']);
        $this->assertEquals('970.00', $result[1]['fee']);
    }

    public function test_allocateProviderFees_three_providers(): void
    {
        // floating=1000, percents=[6, 4, 2]
        // root: profitPercent = subMinZero(6,4)=2; profit=20
        // mid: profitPercent = subMinZero(4,2)=2; profit=20
        // leaf: profitPercent=2; profit=20; fee=subMinZero(1000, 20)=980
        $result = $this->calculator->allocateProviderFees('1000.00', ['6', '4', '2']);

        $this->assertCount(3, $result);
        $this->assertEquals('20.00', $result[0]['profit']);
        $this->assertEquals('20.00', $result[1]['profit']);
        $this->assertEquals('20.00', $result[2]['profit']);
        $this->assertEquals('980.00', $result[2]['fee']);
    }

    public function test_allocateProviderFees_zero_percent_leaf(): void
    {
        // floating=1000, percents=[3, 0]
        // agent: profitPercent = subMinZero(3, 0) = 3; profit = 30
        // leaf: profitPercent = 0; profit = 0; fee = 0 (because !gtZero(0))
        $result = $this->calculator->allocateProviderFees('1000.00', ['3', '0']);

        $this->assertCount(2, $result);
        $this->assertEquals('30.00', $result[0]['profit']);
        $this->assertEquals('0.00', $result[1]['profit']);
        $this->assertEquals('0', $result[1]['fee']);
    }

    public function test_allocateProviderFees_inverted_spread(): void
    {
        // child percent > parent percent → agent profit clamped to 0
        $result = $this->calculator->allocateProviderFees('1000.00', ['2', '5']);

        $this->assertEquals('0.00', $result[0]['profit']); // subMinZero(2, 5) = 0
    }

    // ===== allocateMerchantFees =====

    public function test_allocateMerchantFees_single_merchant(): void
    {
        // amount=1000, percent=[5]
        // leaf: fee = mulPercent(1000, 5) = 50; profit = 0
        $result = $this->calculator->allocateMerchantFees('1000.00', ['5']);

        $this->assertCount(1, $result);
        $this->assertEquals('0', $result[0]['profit']);
        $this->assertEquals('50.00', $result[0]['fee']);
    }

    public function test_allocateMerchantFees_two_merchants(): void
    {
        // amount=1000, percents=[3, 5] (root=3%, leaf=5%)
        // agent: profitPercent = subMinZero(5, 3) = 2; profit = mulPercent(1000, 2) = 20
        // leaf: fee = mulPercent(1000, 5) = 50; profit = 0
        $result = $this->calculator->allocateMerchantFees('1000.00', ['3', '5']);

        $this->assertCount(2, $result);
        $this->assertEquals('20.00', $result[0]['profit']);
        $this->assertEquals('0', $result[0]['fee']);
        $this->assertEquals('0', $result[1]['profit']);
        $this->assertEquals('50.00', $result[1]['fee']);
    }

    public function test_allocateMerchantFees_three_merchants(): void
    {
        // amount=1000, percents=[2, 4, 6]
        // root: profitPercent = subMinZero(4, 2) = 2; profit = 20
        // mid: profitPercent = subMinZero(6, 4) = 2; profit = 20
        // leaf: fee = mulPercent(1000, 6) = 60; profit = 0
        $result = $this->calculator->allocateMerchantFees('1000.00', ['2', '4', '6']);

        $this->assertCount(3, $result);
        $this->assertEquals('20.00', $result[0]['profit']);
        $this->assertEquals('20.00', $result[1]['profit']);
        $this->assertEquals('0', $result[2]['profit']);
        $this->assertEquals('60.00', $result[2]['fee']);
    }

    public function test_allocateMerchantFees_direction_opposite_to_provider(): void
    {
        // Merchant: child% - parent% (ascending)
        // Provider: parent% - child% (descending)
        // Same percents, different direction
        $merchantResult = $this->calculator->allocateMerchantFees('1000.00', ['3', '5']);
        $providerResult = $this->calculator->allocateProviderFees('1000.00', ['5', '3']);

        // Merchant agent profit: subMinZero(5, 3) = 2% → 20
        // Provider agent profit: subMinZero(5, 3) = 2% → 20
        // Same agent profit amount because the spread is identical
        $this->assertEquals($merchantResult[0]['profit'], $providerResult[0]['profit']);

        // But leaf behavior differs:
        // Merchant leaf: profit=0, fee=50
        // Provider leaf: profit=30, fee=970
        $this->assertEquals('0', $merchantResult[1]['profit']);
        $this->assertEquals('50.00', $merchantResult[1]['fee']);
        $this->assertEquals('30.00', $providerResult[1]['profit']);
        $this->assertEquals('970.00', $providerResult[1]['fee']);
    }

    // ===== calculatePaufenSystemProfit =====

    public function test_calculatePaufenSystemProfit_basic(): void
    {
        // amount=1000, floating=1000, merchant%=5, provider%=3
        // systemPercent = subMinZero(5, 3) = 2
        // gross = mulPercent(1000, 2) = 20
        // delta = absDelta(1000, 1000) = 0
        // profit = subMinZero(20, 0) = 20
        $result = $this->calculator->calculatePaufenSystemProfit('1000.00', '1000.00', '5', '3');

        $this->assertEquals('20.00', $result);
    }

    public function test_calculatePaufenSystemProfit_amount_not_equal_floating(): void
    {
        // amount=1000, floating=990, merchant%=5, provider%=3
        // systemPercent = 2; gross = 20; delta = absDelta(1000, 990) = 10
        // profit = subMinZero(20, 10) = 10
        $result = $this->calculator->calculatePaufenSystemProfit('1000.00', '990.00', '5', '3');

        $this->assertEquals('10.00', $result);
    }

    public function test_calculatePaufenSystemProfit_delta_exceeds_gross(): void
    {
        // amount=1000, floating=950, merchant%=5, provider%=3
        // gross = 20; delta = 50
        // profit = subMinZero(20, 50) = 0
        $result = $this->calculator->calculatePaufenSystemProfit('1000.00', '950.00', '5', '3');

        $this->assertEquals('0', $result);
    }

    public function test_calculatePaufenSystemProfit_equal_percents(): void
    {
        // systemPercent = subMinZero(5, 5) = 0; gross = 0
        // profit = subMinZero(0, 0) = 0
        $result = $this->calculator->calculatePaufenSystemProfit('1000.00', '1000.00', '5', '5');

        $this->assertEquals('0', $result);
    }

    public function test_calculatePaufenSystemProfit_provider_exceeds_merchant(): void
    {
        // systemPercent = subMinZero(3, 5) = 0; gross = 0
        $result = $this->calculator->calculatePaufenSystemProfit('1000.00', '1000.00', '3', '5');

        $this->assertEquals('0', $result);
    }

    // ===== scaleFeesProportionally =====

    public function test_scaleFeesProportionally_half_ratio(): void
    {
        $parentFees = [
            ['profit' => '100.00', 'actual_profit' => '80.00', 'fee' => '50.00', 'actual_fee' => '40.00'],
        ];

        $result = $this->calculator->scaleFeesProportionally($parentFees, '500.00', '1000.00');

        $this->assertCount(1, $result);
        $this->assertEquals('50.0000', $result[0]['profit']);
        $this->assertEquals('40.0000', $result[0]['actual_profit']);
        $this->assertEquals('25.0000', $result[0]['fee']);
        $this->assertEquals('20.0000', $result[0]['actual_fee']);
    }

    public function test_scaleFeesProportionally_full_ratio(): void
    {
        $parentFees = [
            ['profit' => '100.00', 'actual_profit' => '80.00', 'fee' => '50.00', 'actual_fee' => '40.00'],
        ];

        $result = $this->calculator->scaleFeesProportionally($parentFees, '1000.00', '1000.00');

        $this->assertEquals('100.0000', $result[0]['profit']);
        $this->assertEquals('80.0000', $result[0]['actual_profit']);
        $this->assertEquals('50.0000', $result[0]['fee']);
        $this->assertEquals('40.0000', $result[0]['actual_fee']);
    }

    public function test_scaleFeesProportionally_multiple_rows(): void
    {
        $parentFees = [
            ['profit' => '20.00', 'actual_profit' => '10.00', 'fee' => '0.00', 'actual_fee' => '0.00'],
            ['profit' => '0.00', 'actual_profit' => '0.00', 'fee' => '30.00', 'actual_fee' => '20.00'],
            ['profit' => '15.00', 'actual_profit' => '15.00', 'fee' => '0.00', 'actual_fee' => '0.00'],
        ];

        $result = $this->calculator->scaleFeesProportionally($parentFees, '500.00', '1000.00');

        $this->assertCount(3, $result);
        $this->assertEquals('10.0000', $result[0]['profit']);
        $this->assertEquals('0.0000', $result[1]['profit']);
        $this->assertEquals('15.0000', $result[1]['fee']);
        $this->assertEquals('7.5000', $result[2]['profit']);
    }

    public function test_scaleFeesProportionally_zero_values(): void
    {
        $parentFees = [
            ['profit' => '0.00', 'actual_profit' => '0.00', 'fee' => '0.00', 'actual_fee' => '0.00'],
        ];

        $result = $this->calculator->scaleFeesProportionally($parentFees, '500.00', '1000.00');

        $this->assertEquals('0.0000', $result[0]['profit']);
        $this->assertEquals('0.0000', $result[0]['actual_profit']);
        $this->assertEquals('0.0000', $result[0]['fee']);
        $this->assertEquals('0.0000', $result[0]['actual_fee']);
    }

    public function test_scaleFeesProportionally_uses_bcmath_precision(): void
    {
        // 1/3 ratio — bcMath->div with scale=4 gives 0.3333
        $parentFees = [
            ['profit' => '100.00', 'actual_profit' => '0.00', 'fee' => '0.00', 'actual_fee' => '0.00'],
        ];

        $result = $this->calculator->scaleFeesProportionally($parentFees, '333.33', '1000.00');

        // div(333.33, 1000) = 0.3333; mul(100, 0.3333) = 33.3300
        $this->assertEquals('33.3300', $result[0]['profit']);
    }
}
