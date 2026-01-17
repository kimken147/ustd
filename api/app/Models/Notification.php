<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string name
 */
class Notification extends Model
{

    protected $fillable = ['mobile', 'transaction_id', 'device_id', 'notification','error','need','but'];

    public function tran()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }
}
