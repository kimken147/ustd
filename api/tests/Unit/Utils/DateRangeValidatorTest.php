<?php

namespace Tests\Unit\Utils;

use App\Repository\FeatureToggleRepository;
use App\Utils\DateRangeValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DateRangeValidatorTest extends TestCase
{
    public function test_constructor_sets_dates(): void
    {
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-01-31');

        $validator = new DateRangeValidator($start, $end);

        $this->assertSame($start, $validator->startedAt);
        $this->assertSame($end, $validator->endedAt);
    }

    public function test_parse_with_request_dates(): void
    {
        $request = Request::create('/test', 'GET', [
            'started_at' => '2026-01-01 00:00:00',
            'ended_at' => '2026-01-31 23:59:59',
        ]);

        $validator = DateRangeValidator::parse($request);

        $this->assertEquals('2026-01-01', $validator->startedAt->toDateString());
        $this->assertEquals('2026-01-31', $validator->endedAt->toDateString());
    }

    public function test_parse_uses_default_start(): void
    {
        $request = Request::create('/test', 'GET', [
            'ended_at' => '2026-01-31 23:59:59',
        ]);
        $defaultStart = Carbon::parse('2026-01-01');

        $validator = DateRangeValidator::parse($request, $defaultStart);

        $this->assertSame($defaultStart, $validator->startedAt);
    }

    public function test_parse_uses_default_end(): void
    {
        $request = Request::create('/test', 'GET', [
            'started_at' => '2026-01-01 00:00:00',
        ]);
        $defaultEnd = Carbon::parse('2026-02-28');

        $validator = DateRangeValidator::parse($request, null, $defaultEnd);

        $this->assertSame($defaultEnd, $validator->endedAt);
    }

    public function test_parse_uses_now_when_no_end(): void
    {
        $request = Request::create('/test', 'GET', [
            'started_at' => '2026-01-01 00:00:00',
        ]);

        $validator = DateRangeValidator::parse($request);

        $this->assertEqualsWithDelta(now()->timestamp, $validator->endedAt->timestamp, 2);
    }

    public function test_validate_months_passes_within_range(): void
    {
        $validator = new DateRangeValidator(now()->subMonth(), now());

        $result = $validator->validateMonths(2);

        $this->assertSame($validator, $result);
    }

    public function test_validate_months_aborts_exceeding_range(): void
    {
        $startedAt = now()->addMonths(6);
        $validator = new DateRangeValidator($startedAt, now());

        $this->expectException(HttpException::class);

        $validator->validateMonths(3);
    }

    public function test_validate_days_passes_within_range(): void
    {
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-01-11');
        $validator = new DateRangeValidator($start, $end);

        $result = $validator->validateDays(30);

        $this->assertSame($validator, $result);
    }

    public function test_validate_days_aborts_exceeding_range(): void
    {
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-03-02');
        $validator = new DateRangeValidator($start, $end);

        $this->expectException(HttpException::class);

        $validator->validateDays(30);
    }

    public function test_validate_days_aborts_when_no_start_date(): void
    {
        $validator = new DateRangeValidator(null, now());

        $this->expectException(HttpException::class);

        $validator->validateDays(30);
    }

    public function test_validate_days_returns_self_for_chaining(): void
    {
        $start = Carbon::parse('2026-01-01');
        $end = Carbon::parse('2026-01-10');
        $validator = new DateRangeValidator($start, $end);

        $result = $validator->validateDays(30);

        $this->assertSame($validator, $result);
    }

    public function test_validate_days_from_feature_toggle_passes_when_disabled(): void
    {
        $validator = new DateRangeValidator(now()->subDays(60), now());

        $repo = $this->createMock(FeatureToggleRepository::class);
        $repo->method('enabled')->willReturn(false);

        $result = $validator->validateDaysFromFeatureToggle($repo);

        $this->assertSame($validator, $result);
    }

    public function test_validate_days_from_feature_toggle_passes_within_range(): void
    {
        $validator = new DateRangeValidator(now()->subDays(10), now());

        $repo = $this->createMock(FeatureToggleRepository::class);
        $repo->method('enabled')->willReturn(true);
        $repo->method('valueOf')->willReturn(30);

        $result = $validator->validateDaysFromFeatureToggle($repo);

        $this->assertSame($validator, $result);
    }

    public function test_validate_days_from_feature_toggle_aborts_exceeding_range(): void
    {
        $startedAt = now()->addDays(60);
        $validator = new DateRangeValidator($startedAt, now());

        $repo = $this->createMock(FeatureToggleRepository::class);
        $repo->method('enabled')->willReturn(true);
        $repo->method('valueOf')->willReturn(30);

        $this->expectException(HttpException::class);

        $validator->validateDaysFromFeatureToggle($repo);
    }
}
