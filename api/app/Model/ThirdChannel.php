<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class ThirdChannel extends Model
{
    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;

    const TYPE_DEPOSIT_WITHDRAW = 1;
    const TYPE_DEPOSIT_ONLY = 2;
    const TYPE_WITHDRAW_ONLY = 3;

    protected $casts = [
        // 'status'       => 'boolean',
        'enable_system_order_number' => 'boolean',
        'use_third_cashier_url' => 'boolean',
    ];

    protected $fillable = [
        'id',
        'name',
        'class',
        'status',
        'notify_balance',
        'auto_daifu_threshold',
        'auto_daifu_threshold_min',
        'use_third_cashier_url',
        'custom_url',
        'cashier_mode',
        'white_ip',
        'enable_system_order_number',
        'type',
        'channel_code',
        'balance',
        'merchant_id',
        'key',
        'key2',
        'key3'
    ];

    protected $table = 'thirdchannel';

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_code');
    }

    public function merchants()
    {
        return $this->hasMany(MerchantThirdChannel::class, 'thirdchannel_id');
    }

    public function canDaifu()
    {
        return in_array($this->type, [self::TYPE_DEPOSIT_WITHDRAW, self::TYPE_WITHDRAW_ONLY]);
    }

    public function needsManualReview(float $amount): bool
    {
        return $amount < $this->auto_daifu_threshold_min
            || $amount > $this->auto_daifu_threshold_max;
    }
}
