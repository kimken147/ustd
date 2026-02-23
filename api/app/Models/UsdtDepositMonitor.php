<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsdtDepositMonitor extends Model
{
    const STATUS_PENDING = 0;
    const STATUS_MATCHED = 1;
    const STATUS_CONFIRMED = 2;
    const STATUS_EXPIRED = 3;

    protected $fillable = [
        'transaction_id',
        'user_channel_account_id',
        'address',
        'chain_network',
        'expected_amount',
        'received_amount',
        'tx_hash',
        'status',
        'expires_at',
        'matched_at',
        'confirmed_at',
        'last_polled_at',
    ];

    protected $casts = [
        'expected_amount' => 'decimal:6',
        'received_amount' => 'decimal:6',
        'expires_at' => 'datetime',
        'matched_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'last_polled_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function userChannelAccount()
    {
        return $this->belongsTo(UserChannelAccount::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
