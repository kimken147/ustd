<?php

namespace App\Utils;

use App\Models\FeatureToggle;
use App\Repository\FeatureToggleRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class DateRangeValidator
{
    public ?Carbon $startedAt;
    public Carbon $endedAt;

    public function __construct(?Carbon $startedAt, Carbon $endedAt)
    {
        $this->startedAt = $startedAt;
        $this->endedAt = $endedAt;
    }

    public static function parse(Request $request, ?Carbon $defaultStart = null, ?Carbon $defaultEnd = null): self
    {
        $tz = config('app.timezone');

        $startedAt = $request->started_at
            ? Carbon::make($request->started_at)->tz($tz)
            : $defaultStart;

        $endedAt = $request->ended_at
            ? Carbon::make($request->ended_at)->tz($tz)
            : ($defaultEnd ?? now());

        return new self($startedAt, $endedAt);
    }

    public function validateMonths(int $maxMonths, string $message = '查无资料'): self
    {
        abort_if(
            now()->diffInMonths($this->startedAt) > $maxMonths,
            Response::HTTP_BAD_REQUEST,
            $message
        );

        return $this;
    }

    public function validateDays(int $maxDays, string $message = '时间区间最多一次筛选一个月，请重新调整时间'): self
    {
        abort_if(
            !$this->startedAt || $this->startedAt->diffInDays($this->endedAt) > $maxDays,
            Response::HTTP_BAD_REQUEST,
            $message
        );

        return $this;
    }

    public function validateDaysFromFeatureToggle(
        FeatureToggleRepository $repo,
        string $toggle = FeatureToggle::VISIABLE_DAYS_OF_PROVIDER_TRANSACTIONS,
        int $default = 30,
        string $message = '查无资料'
    ): self {
        abort_if(
            $repo->enabled($toggle) &&
            now()->diffInDays($this->startedAt) > $repo->valueOf($toggle, $default),
            Response::HTTP_BAD_REQUEST,
            $message
        );

        return $this;
    }
}
