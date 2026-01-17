<?php

namespace App\Model;

use App\Utils\BCMathUtil;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Wallet extends Model
{
    use SoftDeletes;

    const STATUS_DISABLE = 0;

    const STATUS_ENABLE = 1;

    protected $table = 'wallets';

    protected $fillable = [
        'user_id', 'status',
        'balance', 'profit', 'frozen_balance',
        'withdraw_fee', 'withdraw_fee_percent', 'withdraw_profit_fee', 'agency_withdraw_fee', 'agency_withdraw_fee_dollar',
        'additional_withdraw_fee', 'additional_agency_withdraw_fee',
        'withdraw_min_amount', 'withdraw_max_amount',
        'withdraw_profit_min_amount', 'withdraw_profit_max_amount',
        'agency_withdraw_min_amount', 'agency_withdraw_max_amount',
    ];

    protected $appends = [
        'available_balance',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function getAvailableBalanceAttribute()
    {
        $bcMatch = app(BCMathUtil::class);

        return $bcMatch->subMinZero($this->balance, $this->frozen_balance);
    }

    public function calculateTotalWithdrawAmount($amount, $hasAdditionalFee = false)
    {
        $bcMath = app(BCMathUtil::class);

        return $bcMath->sum([
            $amount,
            $this->calculateTotalWithdrawFee($amount, $hasAdditionalFee)
        ]);
    }

    public function calculateTotalWithdrawFee($amount, $hasAdditionalFee = false, $type = "balance")
    {
        $bcMath = app(BCMathUtil::class);

        return $bcMath->sum([
            $bcMath->mulPercent($amount, $this->withdraw_fee_percent),
            $type == "balance" ? $this->withdraw_fee : $this->withdraw_profit_fee,
            $hasAdditionalFee ? $this->additional_withdraw_fee : 0
        ]);
    }

    public function calculateTotalAgencyWithdrawAmount($amount, $hasAdditionalFee = false)
    {
        $bcMath = app(BCMathUtil::class);

        return $bcMath->sum([
            $amount,
            $this->calculateTotalAgencyWithdrawFee($amount, $hasAdditionalFee)
        ]);
    }

    public function calculateTotalAgencyWithdrawFee($amount, $hasAdditionalFee = false)
    {
        $bcMath = app(BCMathUtil::class);

        return $bcMath->sum([
            $bcMath->mulPercent($amount, $this->agency_withdraw_fee),
            $this->agency_withdraw_fee_dollar,
            $hasAdditionalFee ? $this->additional_agency_withdraw_fee : 0
        ]);
    }
}
