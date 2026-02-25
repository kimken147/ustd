<?php

namespace Tests\Unit\Utils;

use App\Utils\FloatUtil;
use Tests\TestCase;

class FloatUtilTest extends TestCase
{
    private FloatUtil $floatUtil;

    protected function setUp(): void
    {
        parent::setUp();
        $this->floatUtil = new FloatUtil();
    }

    public function test_integer_returns_false(): void
    {
        $this->assertFalse($this->floatUtil->numberHasFloat(100));
    }

    public function test_float_returns_true(): void
    {
        $this->assertTrue($this->floatUtil->numberHasFloat(100.50));
    }

    public function test_string_integer_returns_false(): void
    {
        $this->assertFalse($this->floatUtil->numberHasFloat('100'));
    }

    public function test_string_float_returns_true(): void
    {
        $this->assertTrue($this->floatUtil->numberHasFloat('100.50'));
    }

    public function test_zero_returns_false(): void
    {
        $this->assertFalse($this->floatUtil->numberHasFloat(0));
    }

    public function test_negative_integer_returns_false(): void
    {
        $this->assertFalse($this->floatUtil->numberHasFloat(-5));
    }

    public function test_negative_float_returns_true(): void
    {
        $this->assertTrue($this->floatUtil->numberHasFloat(-5.5));
    }

    public function test_non_numeric_returns_false(): void
    {
        $this->assertFalse($this->floatUtil->numberHasFloat('abc'));
    }
}
