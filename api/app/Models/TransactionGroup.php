<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionGroup extends Model
{
    protected $fillable = ['transaction_type', 'owner_id', 'worker_id','personal_enable'];

    protected $casts = [
        'personal_enable'                           => 'boolean',
    ];
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function userChannelAccounts()
    {
        return $this->belongsToMany(UserChannelAccount::class);
    }
}
