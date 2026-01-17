<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DevicePayingTransaction extends Model
{
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = ['device_id', 'user_channel_account_id', 'transaction_id', 'amount'];

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
