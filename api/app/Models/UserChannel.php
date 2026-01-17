<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int user_id
 * @property string fee_percent
 * @property array detail
 * @property User user
 * @property string min_amount
 * @property string max_amount
 * @property ChannelGroup channelGroup
 * @property int channel_group_id
 * @property bool real_name_enable
 */
class UserChannel extends Model
{

    const STATUS_DISABLED = 0;
    const STATUS_ENABLED = 1;
    protected $casts = [
        'floating_enable'  => 'boolean',
        'detail'           => 'json',
        'real_name_enable' => 'boolean',
    ];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'channel_group_id',
        'status',
        'min_amount',
        'max_amount',
        'fee_percent',
        'floating_enable',
        'detail',
        'real_name_enable',
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
    ];
    protected $table = 'user_channels';

    public function channelGroup()
    {
        return $this->belongsTo(ChannelGroup::class);
    }

    public function isDisabled()
    {
        return (int) $this->status === self::STATUS_DISABLED;
    }

    public function isFixAmount()
    {
        return $this->min_amount === $this->max_amount;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function userChannelAccounts()
    {
        return $this->hasMany(UserChannelAccount::class);
    }
}
