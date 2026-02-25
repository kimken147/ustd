<?php

namespace Tests\Unit\Utils;

use App\Utils\AmountDisplayTransformer;
use Tests\TestCase;

class AmountDisplayTransformerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure APP_REGION is not 'vn' so precise=2 for most tests
        app()['config']->set('app.region', null);
        putenv('APP_REGION');
    }

    public function test_default_formatting(): void
    {
        $this->assertEquals('1,234.56', AmountDisplayTransformer::transform(1234.56));
    }

    public function test_large_number(): void
    {
        $this->assertEquals('1,234,567.89', AmountDisplayTransformer::transform(1234567.89));
    }

    public function test_zero(): void
    {
        $this->assertEquals('0.00', AmountDisplayTransformer::transform(0));
    }

    public function test_custom_separator(): void
    {
        $this->assertEquals('1.234.56', AmountDisplayTransformer::transform(1234.56, '.'));
    }

    public function test_no_separator(): void
    {
        $this->assertEquals('1234.56', AmountDisplayTransformer::transform(1234.56, ''));
    }

    public function test_default_value(): void
    {
        $this->assertEquals('0.00', AmountDisplayTransformer::transform());
    }
}
