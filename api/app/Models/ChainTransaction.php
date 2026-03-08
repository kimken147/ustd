<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChainTransaction extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_MATCHED = 'matched';
    const STATUS_UNMATCHED = 'unmatched';
    const STATUS_IGNORED = 'ignored';

    const DIRECTION_IN = 'in';
    const DIRECTION_OUT = 'out';

    protected $fillable = [
        'tx_hash',
        'user_channel_account_id',
        'direction',
        'from_address',
        'to_address',
        'amount',
        'block_number',
        'block_timestamp',
        'confirmations',
        'match_status',
        'matched_transaction_id',
        'matched_at',
        'matched_by',
        'note',
        'raw_data',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
        'block_timestamp' => 'datetime',
        'matched_at' => 'datetime',
        'raw_data' => 'array',
    ];

    public function userChannelAccount()
    {
        return $this->belongsTo(UserChannelAccount::class);
    }

    public function matchedTransaction()
    {
        return $this->belongsTo(Transaction::class, 'matched_transaction_id');
    }

    public function matchedByUser()
    {
        return $this->belongsTo(User::class, 'matched_by');
    }

    public function scopePending($query)
    {
        return $query->where('match_status', self::STATUS_PENDING);
    }

    public function scopeUnmatched($query)
    {
        return $query->where('match_status', self::STATUS_UNMATCHED);
    }
}
