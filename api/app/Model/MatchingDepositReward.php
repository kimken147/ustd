<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int reward_unit
 * @property string reward_amount
 * @property string min_amount
 * @property string max_amount
 */
class MatchingDepositReward extends Model
{

    const REWARD_UNIT_SINGLE = 1;
    const REWARD_UNIT_PERCENT = 2;

    protected $fillable = ['min_amount', 'max_amount', 'reward_amount', 'reward_unit'];
}
