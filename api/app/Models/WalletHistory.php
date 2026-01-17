<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WalletHistory extends Model
{
    use SoftDeletes;

    const TYPE_SYSTEM_ADJUSTING = 1;
    const TYPE_TRANSFER = 2;
    const TYPE_DEPOSIT = 3;
    const TYPE_WITHHOLD = 4;
    const TYPE_WITHHOLD_ROLLBACK = 5;
    const TYPE_MATCHING_DEPOSIT_REWARD = 6;
    const TYPE_TRANSACTION_REWARD = 7;
    const TYPE_DEPOSIT_DEDUCT_FROZEN_BALANCE = 8;
    const TYPE_DEPOSIT_PROFIT = 9;
    const TYPE_SYSTEM_ADJUSTING_PROFIT = 10;
    const TYPE_SYSTEM_ADJUSTING_FROZEN_BALANCE = 11;
    const TYPE_WITHDRAW = 12;
    const TYPE_WITHDRAW_ROLLBACK = 13;
    const TYPE_DEPOSIT_ROLLBACK = 14;

    protected $fillable = [
        'user_id', 'operator_id', 'type',
        'delta', 'result', 'note',
    ];

    protected $casts = [
        'delta'  => 'json',
        'result' => 'json',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class,'operator_id');
    }
}
