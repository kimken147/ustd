<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MerchantThirdChannel extends Model
{
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;

    protected $fillable = [
        'thirdchannel_id', 'owner_id','deposit_fee_percent',
        'withdraw_fee','daifu_fee_percent','deposit_min','deposit_max','daifu_min','daifu_max'
    ];

    protected $table = 'merchant_thirdchannel';

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function thirdChannel()
    {
        return $this->belongsTo(ThirdChannel::class,'thirdchannel_id');
    }

    public function userChannelAccounts()
    {
        return $this->belongsToMany(UserChannelAccount::class);
    }
}
