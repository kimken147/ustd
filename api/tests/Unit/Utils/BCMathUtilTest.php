<?php

namespace Tests\Unit\Utils;

use App\Utils\BCMathUtil;
use Tests\TestCase;

class BCMathUtilTest extends TestCase
{
    private BCMathUtil $bc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bc = new BCMathUtil();
    }

    // ---------------------------------------------------------------
    // Constants
    // ---------------------------------------------------------------

    public function test_scale_constant_is_2(): void
    {
        $this->assertSame(2, BCMathUtil::SCALE);
    }

    public function test_more_scale_constant_is_4(): void
    {
        $this->assertSame(4, BCMathUtil::MORE_SCALE);
    }

    // ---------------------------------------------------------------
    // add()
    // ---------------------------------------------------------------

    public static function addProvider(): array
    {
        return [
            'positive + positive'       => ['1.50', '2.30', 2, '3.80'],
            'positive + negative'       => ['10.00', '-3.50', 2, '6.50'],
            'negative + negative'       => ['-1.00', '-2.00', 2, '-3.00'],
            'zero + zero'              => ['0', '0', 2, '0.00'],
            'zero + positive'          => ['0', '5.55', 2, '5.55'],
            'large numbers'            => ['999999999.99', '0.01', 2, '1000000000.00'],
            'scale 4'                  => ['1.1111', '2.2222', 4, '3.3333'],
            'truncation not rounding'  => ['1.005', '0', 2, '1.00'],
        ];
    }

    /**
     * @dataProvider addProvider
     */
    public function test_add($left, $right, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->add($left, $right, $scale));
    }

    public function test_add_default_scale_is_2(): void
    {
        $this->assertSame('3.33', $this->bc->add('1.111', '2.222'));
    }

    // ---------------------------------------------------------------
    // sum()
    // ---------------------------------------------------------------

    public static function sumProvider(): array
    {
        return [
            'multiple values'   => [['1.00', '2.00', '3.00'], 2, '6.00'],
            'single value'      => [['5.55'], 2, '5.55'],
            'empty array'       => [[], 2, 0],
            'with negatives'    => [['10.00', '-3.00', '-2.00'], 2, '5.00'],
            'scale 4'           => [['1.1111', '2.2222', '3.3333'], 4, '6.6666'],
            'zeros'             => [['0', '0', '0'], 2, '0.00'],
        ];
    }

    /**
     * @dataProvider sumProvider
     */
    public function test_sum($amountSet, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->sum($amountSet, $scale));
    }

    public function test_sum_default_scale_is_2(): void
    {
        $this->assertSame('3.33', $this->bc->sum(['1.111', '2.222']));
    }

    // ---------------------------------------------------------------
    // sub()
    // ---------------------------------------------------------------

    public static function subProvider(): array
    {
        return [
            'positive - positive'   => ['10.00', '3.50', 2, '6.50'],
            'positive - larger'     => ['3.00', '5.00', 2, '-2.00'],
            'negative - negative'   => ['-5.00', '-3.00', 2, '-2.00'],
            'zero - positive'       => ['0', '1.00', 2, '-1.00'],
            'same values'           => ['5.00', '5.00', 2, '0.00'],
            'scale 4'               => ['10.5555', '3.1111', 4, '7.4444'],
        ];
    }

    /**
     * @dataProvider subProvider
     */
    public function test_sub($left, $right, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->sub($left, $right, $scale));
    }

    public function test_sub_default_scale_is_2(): void
    {
        $this->assertSame('1.11', $this->bc->sub('3.333', '2.222'));
    }

    // ---------------------------------------------------------------
    // mul()
    // ---------------------------------------------------------------

    public static function mulProvider(): array
    {
        return [
            'positive * positive'   => ['2.00', '3.00', 4, '6.0000'],
            'positive * negative'   => ['5.00', '-2.00', 4, '-10.0000'],
            'negative * negative'   => ['-3.00', '-4.00', 4, '12.0000'],
            'by zero'               => ['100.00', '0', 4, '0.0000'],
            'by one'                => ['7.5555', '1', 4, '7.5555'],
            'decimals'              => ['1.5', '1.5', 4, '2.2500'],
            'scale 2'               => ['1.50', '1.50', 2, '2.25'],
        ];
    }

    /**
     * @dataProvider mulProvider
     */
    public function test_mul($left, $right, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->mul($left, $right, $scale));
    }

    public function test_mul_default_scale_is_4(): void
    {
        $this->assertSame('6.0000', $this->bc->mul('2', '3'));
    }

    // ---------------------------------------------------------------
    // div()
    // ---------------------------------------------------------------

    public static function divProvider(): array
    {
        return [
            'even division'       => ['10.00', '2.00', 4, '5.0000'],
            'repeating decimal'   => ['10.00', '3.00', 4, '3.3333'],
            'negative dividend'   => ['-10.00', '3.00', 4, '-3.3333'],
            'negative divisor'    => ['10.00', '-3.00', 4, '-3.3333'],
            'both negative'       => ['-10.00', '-3.00', 4, '3.3333'],
            'zero dividend'       => ['0', '5.00', 4, '0.0000'],
            'scale 2'             => ['10', '3', 2, '3.33'],
            'large numbers'       => ['1000000', '7', 4, '142857.1428'],
        ];
    }

    /**
     * @dataProvider divProvider
     */
    public function test_div($left, $right, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->div($left, $right, $scale));
    }

    public function test_div_default_scale_is_4(): void
    {
        $this->assertSame('3.3333', $this->bc->div('10', '3'));
    }

    // ---------------------------------------------------------------
    // div100()
    // ---------------------------------------------------------------

    public static function div100Provider(): array
    {
        return [
            'whole number'    => ['500', 4, '5.0000'],
            'with decimal'    => ['1.50', 4, '0.0150'],
            'zero'            => ['0', 4, '0.0000'],
            'negative'        => ['-250', 4, '-2.5000'],
            'scale 2'         => ['350', 2, '3.50'],
            'small number'    => ['1', 4, '0.0100'],
        ];
    }

    /**
     * @dataProvider div100Provider
     */
    public function test_div100($left, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->div100($left, $scale));
    }

    public function test_div100_default_scale_is_4(): void
    {
        $this->assertSame('1.0000', $this->bc->div100('100'));
    }

    // ---------------------------------------------------------------
    // mulPercent()
    // ---------------------------------------------------------------

    public static function mulPercentProvider(): array
    {
        return [
            '10% of 100'     => ['100', '10', 2, '10.00'],
            '50% of 200'     => ['200', '50', 2, '100.00'],
            '100% of 50'     => ['50', '100', 2, '50.00'],
            '0% of 100'      => ['100', '0', 2, '0.00'],
            '1% of 1000'     => ['1000', '1', 2, '10.00'],
            '2.5% of 200'    => ['200', '2.5', 2, '5.00'],
            'scale 4'        => ['100', '33.33', 4, '33.3300'],
        ];
    }

    /**
     * @dataProvider mulPercentProvider
     */
    public function test_mul_percent($amount, $percent, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->mulPercent($amount, $percent, $scale));
    }

    public function test_mul_percent_default_scale_is_2(): void
    {
        $this->assertSame('25.00', $this->bc->mulPercent('100', '25'));
    }

    // ---------------------------------------------------------------
    // notEqual()
    // ---------------------------------------------------------------

    public function test_not_equal_returns_true_for_different_values(): void
    {
        $this->assertTrue($this->bc->notEqual('1.00', '2.00'));
    }

    public function test_not_equal_returns_false_for_equal_values(): void
    {
        $this->assertFalse($this->bc->notEqual('1.00', '1.00'));
    }

    public function test_not_equal_respects_scale(): void
    {
        // 1.001 vs 1.002 are equal at scale 2 but different at scale 3
        $this->assertFalse($this->bc->notEqual('1.001', '1.002', 2));
        $this->assertTrue($this->bc->notEqual('1.001', '1.002', 3));
    }

    // ---------------------------------------------------------------
    // max()
    // ---------------------------------------------------------------

    public static function maxProvider(): array
    {
        return [
            'left is larger'    => ['10.00', '5.00', 2, '10.00'],
            'right is larger'   => ['5.00', '10.00', 2, '10.00'],
            'equal values'      => ['5.00', '5.00', 2, '5.00'],
            'negative vs pos'   => ['-5.00', '5.00', 2, '5.00'],
            'both negative'     => ['-10.00', '-5.00', 2, '-5.00'],
            'zero vs positive'  => ['0', '1.00', 2, '1.00'],
            'zero vs negative'  => ['0', '-1.00', 2, '0'],
        ];
    }

    /**
     * @dataProvider maxProvider
     */
    public function test_max($left, $right, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->max($left, $right, $scale));
    }

    // ---------------------------------------------------------------
    // gte()
    // ---------------------------------------------------------------

    public function test_gte_returns_true_when_left_is_greater(): void
    {
        $this->assertTrue($this->bc->gte('10.00', '5.00'));
    }

    public function test_gte_returns_true_when_equal(): void
    {
        $this->assertTrue($this->bc->gte('5.00', '5.00'));
    }

    public function test_gte_returns_false_when_left_is_less(): void
    {
        $this->assertFalse($this->bc->gte('3.00', '5.00'));
    }

    public function test_gte_respects_scale(): void
    {
        // 1.004 vs 1.005 — at scale 2 both truncate to 1.00 so gte is true
        $this->assertTrue($this->bc->gte('1.004', '1.005', 2));
        $this->assertFalse($this->bc->gte('1.004', '1.005', 3));
    }

    // ---------------------------------------------------------------
    // negativeOf()
    // ---------------------------------------------------------------

    public function test_negative_of_positive_returns_negative(): void
    {
        $this->assertSame('-5.00', $this->bc->negativeOf('5.00'));
    }

    public function test_negative_of_negative_returns_negative(): void
    {
        $this->assertSame('-5.00', $this->bc->negativeOf('-5.00'));
    }

    public function test_negative_of_zero_returns_zero(): void
    {
        $this->assertSame('0.00', $this->bc->negativeOf('0.00'));
    }

    public function test_negative_of_respects_scale(): void
    {
        $this->assertSame('-5.5500', $this->bc->negativeOf('5.55', 4));
    }

    // ---------------------------------------------------------------
    // positiveOf()
    // ---------------------------------------------------------------

    public function test_positive_of_negative_returns_positive(): void
    {
        $this->assertSame('5.00', $this->bc->positiveOf('-5.00'));
    }

    public function test_positive_of_positive_returns_positive(): void
    {
        $this->assertSame('5.00', $this->bc->positiveOf('5.00'));
    }

    public function test_positive_of_zero_returns_zero(): void
    {
        $this->assertSame('0.00', $this->bc->positiveOf('0.00'));
    }

    // ---------------------------------------------------------------
    // abs()
    // ---------------------------------------------------------------

    public static function absProvider(): array
    {
        return [
            'positive'  => ['5.00', 2, '5.00'],
            'negative'  => ['-5.00', 2, '5.00'],
            'zero'      => ['0', 2, '0.00'],
            'large neg' => ['-999999.99', 2, '999999.99'],
            'scale 4'   => ['-3.1415', 4, '3.1415'],
        ];
    }

    /**
     * @dataProvider absProvider
     */
    public function test_abs($amount, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->abs($amount, $scale));
    }

    // ---------------------------------------------------------------
    // lt()
    // ---------------------------------------------------------------

    public function test_lt_returns_true_when_left_is_less(): void
    {
        $this->assertTrue($this->bc->lt('3.00', '5.00'));
    }

    public function test_lt_returns_false_when_equal(): void
    {
        $this->assertFalse($this->bc->lt('5.00', '5.00'));
    }

    public function test_lt_returns_false_when_left_is_greater(): void
    {
        $this->assertFalse($this->bc->lt('10.00', '5.00'));
    }

    public function test_lt_with_negative_numbers(): void
    {
        $this->assertTrue($this->bc->lt('-10.00', '-5.00'));
    }

    // ---------------------------------------------------------------
    // lte()
    // ---------------------------------------------------------------

    public function test_lte_returns_true_when_left_is_less(): void
    {
        $this->assertTrue($this->bc->lte('3.00', '5.00'));
    }

    public function test_lte_returns_true_when_equal(): void
    {
        $this->assertTrue($this->bc->lte('5.00', '5.00'));
    }

    public function test_lte_returns_false_when_left_is_greater(): void
    {
        $this->assertFalse($this->bc->lte('10.00', '5.00'));
    }

    // ---------------------------------------------------------------
    // ltZero()
    // ---------------------------------------------------------------

    public function test_lt_zero_returns_true_for_negative(): void
    {
        $this->assertTrue($this->bc->ltZero('-1.00'));
    }

    public function test_lt_zero_returns_false_for_zero(): void
    {
        $this->assertFalse($this->bc->ltZero('0'));
    }

    public function test_lt_zero_returns_false_for_positive(): void
    {
        $this->assertFalse($this->bc->ltZero('1.00'));
    }

    // ---------------------------------------------------------------
    // gt()
    // ---------------------------------------------------------------

    public function test_gt_returns_true_when_left_is_greater(): void
    {
        $this->assertTrue($this->bc->gt('10.00', '5.00'));
    }

    public function test_gt_returns_false_when_equal(): void
    {
        $this->assertFalse($this->bc->gt('5.00', '5.00'));
    }

    public function test_gt_returns_false_when_left_is_less(): void
    {
        $this->assertFalse($this->bc->gt('3.00', '5.00'));
    }

    public function test_gt_with_negative_numbers(): void
    {
        $this->assertTrue($this->bc->gt('-5.00', '-10.00'));
    }

    // ---------------------------------------------------------------
    // gtZero()
    // ---------------------------------------------------------------

    public function test_gt_zero_returns_true_for_positive(): void
    {
        $this->assertTrue($this->bc->gtZero('1.00'));
    }

    public function test_gt_zero_returns_false_for_zero(): void
    {
        $this->assertFalse($this->bc->gtZero('0'));
    }

    public function test_gt_zero_returns_false_for_negative(): void
    {
        $this->assertFalse($this->bc->gtZero('-1.00'));
    }

    // ---------------------------------------------------------------
    // different() — raw bccomp return
    // ---------------------------------------------------------------

    public function test_different_returns_1_when_left_is_greater(): void
    {
        $this->assertSame(1, $this->bc->different('10.00', '5.00'));
    }

    public function test_different_returns_negative_1_when_left_is_less(): void
    {
        $this->assertSame(-1, $this->bc->different('5.00', '10.00'));
    }

    public function test_different_returns_0_when_equal(): void
    {
        $this->assertSame(0, $this->bc->different('5.00', '5.00'));
    }

    // ---------------------------------------------------------------
    // absDelta()
    // ---------------------------------------------------------------

    public static function absDeltaProvider(): array
    {
        return [
            'left > right'      => ['10.00', '3.00', 2, '7.00'],
            'right > left'      => ['3.00', '10.00', 2, '7.00'],
            'equal'             => ['5.00', '5.00', 2, '0.00'],
            'negative values'   => ['-5.00', '-10.00', 2, '5.00'],
            'mixed signs'       => ['-5.00', '5.00', 2, '10.00'],
        ];
    }

    /**
     * @dataProvider absDeltaProvider
     */
    public function test_abs_delta($left, $right, $scale, $expected): void
    {
        $this->assertSame($expected, $this->bc->absDelta($left, $right, $scale));
    }

    // ---------------------------------------------------------------
    // subMinZero()
    // ---------------------------------------------------------------

    public static function subMinZeroProvider(): array
    {
        return [
            'positive result'       => ['10.00', '3.00', 2, '7.00'],
            'zero result'           => ['5.00', '5.00', 2, '0.00'],
            'would-be negative'     => ['3.00', '10.00', 2, '0.00'],
            'large subtrahend'      => ['1.00', '1000.00', 2, '0.00'],
        ];
    }

    /**
     * @dataProvider subMinZeroProvider
     */
    public function test_sub_min_zero($left, $right, $scale, $expected): void
    {
        $result = $this->bc->subMinZero($left, $right, $scale);
        // Compare numerically to handle '0' vs '0.00' from max()
        $this->assertSame(0, bccomp($result, $expected, $scale));
    }

    // ---------------------------------------------------------------
    // eq()
    // ---------------------------------------------------------------

    public function test_eq_returns_true_for_equal_values(): void
    {
        $this->assertTrue($this->bc->eq('5.00', '5.00'));
    }

    public function test_eq_returns_false_for_different_values(): void
    {
        $this->assertFalse($this->bc->eq('5.00', '5.01'));
    }

    public function test_eq_respects_scale(): void
    {
        // At scale 2: 5.001 and 5.002 truncate to 5.00 — equal
        $this->assertTrue($this->bc->eq('5.001', '5.002', 2));
        // At scale 3: they differ
        $this->assertFalse($this->bc->eq('5.001', '5.002', 3));
    }

    public function test_eq_zero_representations(): void
    {
        $this->assertTrue($this->bc->eq('0', '0.00'));
        $this->assertTrue($this->bc->eq('0.00', '0.00'));
    }

    // ---------------------------------------------------------------
    // comp() — raw bccomp return
    // ---------------------------------------------------------------

    public function test_comp_returns_1_when_left_is_greater(): void
    {
        $this->assertSame(1, $this->bc->comp('10.00', '5.00'));
    }

    public function test_comp_returns_negative_1_when_left_is_less(): void
    {
        $this->assertSame(-1, $this->bc->comp('5.00', '10.00'));
    }

    public function test_comp_returns_0_when_equal(): void
    {
        $this->assertSame(0, $this->bc->comp('5.00', '5.00'));
    }

    public function test_comp_respects_scale(): void
    {
        $this->assertSame(0, $this->bc->comp('1.001', '1.002', 2));
        $this->assertSame(-1, $this->bc->comp('1.001', '1.002', 3));
    }

    // ---------------------------------------------------------------
    // Edge cases across methods
    // ---------------------------------------------------------------

    public function test_arithmetic_with_very_large_numbers(): void
    {
        $large = '99999999999999999999.99';
        $this->assertSame('199999999999999999999.98', $this->bc->add($large, $large));
        $this->assertSame('0.00', $this->bc->sub($large, $large));
    }

    public function test_chained_operations_precision(): void
    {
        // Simulate: 100 * 3.33% should be 3.33
        $result = $this->bc->mulPercent('100', '3.33');
        $this->assertSame('3.33', $result);
    }

    public function test_comparison_methods_with_zero_string(): void
    {
        $this->assertTrue($this->bc->eq('0', '0.00'));
        $this->assertTrue($this->bc->gte('0', '0.00'));
        $this->assertTrue($this->bc->lte('0', '0.00'));
        $this->assertFalse($this->bc->gt('0', '0.00'));
        $this->assertFalse($this->bc->lt('0', '0.00'));
        $this->assertFalse($this->bc->ltZero('0'));
        $this->assertFalse($this->bc->gtZero('0'));
    }
}
