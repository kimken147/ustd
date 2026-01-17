<?php


namespace App\Utils;


class BCMathUtil
{

    const SCALE = 2;
    const MORE_SCALE = 4;

    public function add($left, $right, $scale = self::SCALE)
    {
        return bcadd($left, $right, $scale);
    }

    public function sum(array $amountSet, $scale = self::SCALE)
    {
        return array_reduce($amountSet, function ($sum, $amount) use ($scale) {
            return bcadd($sum, $amount, $scale);
        }, 0);
    }

    public function sub($left, $right, $scale = self::SCALE)
    {
        return bcsub($left, $right, $scale);
    }

    public function mul($left, $right, $scale = self::MORE_SCALE)
    {
        return bcmul($left, $right, $scale);
    }

    public function div($left, $right, $scale = self::MORE_SCALE)
    {
        return bcdiv($left, $right, $scale);
    }

    public function div100($left, $scale = self::MORE_SCALE)
    {
        return $this->div($left, 100, $scale);
    }

    public function mulPercent($amount, $percent, $scale = self::SCALE)
    {
        return $this->mul($amount, $this->div100($percent), $scale);
    }

    public function notEqual($left, $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale) !== 0;
    }

    public function max($left, $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale) >= 0 ? $left : $right;
    }

    public function gte($left, $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale) >= 0;
    }

    public function negativeOf(string $amount, $scale = self::SCALE)
    {
        return bcmul($this->abs($amount), '-1', $scale);
    }

    public function positiveOf(string $amount, $scale = self::SCALE)
    {
        return $this->abs($amount, $scale);
    }

    public function abs(string $amount, $scale = self::SCALE)
    {
        if ($this->gte($amount, 0)) {
            return bcmul($amount, '1', $scale);
        }

        return bcmul($amount, '-1', $scale);
    }

    public function lt(string $left, string $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale) < 0;
    }

    public function lte(string $left, string $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale) <= 0;
    }

    public function ltZero(string $left, $scale = self::SCALE)
    {
        return bccomp($left, 0, $scale) < 0;
    }

    public function gt(string $left, string $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale) > 0;
    }

    public function gtZero(string $left, $scale = self::SCALE)
    {
        return bccomp($left, 0, $scale) > 0;
    }

    public function different(string $left, string $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale);
    }

    public function absDelta(string $left, string $right, $scale = self::SCALE)
    {
        return $this->abs($this->sub($left, $right, $scale), $scale);
    }

    public function subMinZero(string $left, string $right, $scale = self::SCALE)
    {
        return $this->max(0, $this->sub($left, $right, $scale), $scale);
    }

    public function eq(string $left, string $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale) === 0;
    }

    public function comp(string $left, string $right, $scale = self::SCALE)
    {
        return bccomp($left, $right, $scale);
    }
}
