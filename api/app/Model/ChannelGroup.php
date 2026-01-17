<?php

namespace App\Model;

use App\Utils\AmountDisplayTransformer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string channel_code
 * @property Channel|null channel
 * @property string amount_description
 */
class ChannelGroup extends Model
{
    use SoftDeletes;

    protected $fillable = ['channel_code', 'fixed_amount'];

    protected $casts = ['fixed_amount' => 'bool'];

    public function channelAmounts()
    {
        return $this->hasMany(ChannelAmount::class);
    }

    public function channelAmount()
    {
        return $this->hasOne(ChannelAmount::class);
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_code', 'code');
    }

    public function userChannels()
    {
        return $this->hasMany(UserChannel::class);
    }

    public function getAmountDescriptionAttribute()
    {
        if (!$this->channelAmounts()->exists()) {
            return null;
        }

        $channelAmount = $this->channelAmounts()->first();

        return $channelAmount->amount_description;
    }
}
