<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int reward_unit
 * @property string reward_amount
 * @property string min_amount
 * @property string max_amount
 * @property Carbon started_at
 * @property Carbon ended_at
 */
class TransactionReward extends Model
{

    const REWARD_UNIT_SINGLE = 1;
    const REWARD_UNIT_PERCENT = 2;

    protected $fillable = ['min_amount', 'max_amount', 'reward_amount', 'reward_unit', 'started_at', 'ended_at'];

    public function getTimeRangeAttribute()
    {
        return "{$this->started_at} ~ {$this->ended_at}";
    }
}
