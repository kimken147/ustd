<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundTransferLog extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_SUCCESS = 'success';
    const STATUS_FAILED = 'failed';

    protected $fillable = [
        'batch_id',
        'source_account_id',
        'target_account_id',
        'source_address',
        'target_address',
        'amount',
        'chain_network',
        'tx_hash',
        'status',
        'error_message',
        'operator_id',
    ];

    protected $casts = [
        'amount' => 'decimal:6',
    ];

    public function sourceAccount(): BelongsTo
    {
        return $this->belongsTo(UserChannelAccount::class, 'source_account_id');
    }

    public function targetAccount(): BelongsTo
    {
        return $this->belongsTo(UserChannelAccount::class, 'target_account_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
