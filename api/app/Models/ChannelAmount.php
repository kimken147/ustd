<?php

namespace App\Models;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property Channel $channel
 * @property ChannelGroup channelGroup
 * @property int channel_group_id
 */
class ChannelAmount extends Model
{
    use SoftDeletes;

    protected $fillable = ['channel_code', 'min_amount', 'max_amount', 'fixed_amount'];

    protected $casts = ['fixed_amount' => 'json'];

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_code', 'code');
    }

    public function userChannelAccounts()
    {
        return $this->hasMany(UserChannelAccount::class);
    }

    public function channelGroup()
    {
        return $this->belongsTo(ChannelGroup::class);
    }

    public function getAmountDescriptionAttribute()
    {
        if ($this->fixed_amount) {
            return implode(',', array_map(function ($amount) {
                return AmountDisplayTransformer::transform($amount);
            }, $this->fixed_amount));
        }

        $formattedMinAmount = AmountDisplayTransformer::transform($this->min_amount);
        $formattedMaxAmount = AmountDisplayTransformer::transform($this->max_amount);

        return "$formattedMinAmount~$formattedMaxAmount";
    }

    public function setFixedAmountAttribute($value)
    {
        $this->attributes['fixed_amount'] = json_encode($value);
    }

    public function accounts()
    {
        return $this->hasMany(UserChannelAccount::class);
    }
}
