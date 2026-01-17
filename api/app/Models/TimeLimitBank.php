<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string bank_name
 * @property int status
 * @property Carbon started_at
 * @property Carbon ended_at
 */
class TimeLimitBank extends Model
{

    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;
    protected $fillable = ['bank_id', 'bank_name', 'status', 'started_at', 'ended_at'];

    protected $dates = ['started_at', 'ended_at'];

    protected $casts = [
        'status' => 'int',
    ];

    public function timeLimitBanks()
    {
        return $this->hasMany(TimeLimitBank::class, 'bank_id', 'bank_id');
    }

    public function bank(){
        return $this->belongsTo(Bank::class);
    }
}
