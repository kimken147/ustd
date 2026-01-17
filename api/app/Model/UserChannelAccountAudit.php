<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class UserChannelAccountAudit extends Model
{

    protected $casts = [
        'old_value' => 'json',
        'new_value' => 'json',
    ];

    protected $fillable = [
        'user_channel_account_id',
        'old_value',
        'new_value',
        'updated_by_user_id',
        'updated_by_transaction_id',
        'note'
    ];

    public function userChannelAccount()
    {
        return $this->belongsTo(UserChannelAccount::class);
    }

    public function updateByUser()
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function updateByTransaction()
    {
        return $this->belongsTo(Transaction::class, 'updated_by_transaction_id');
    }
}
