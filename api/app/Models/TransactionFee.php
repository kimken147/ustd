<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int user_id
 * @property User|null user
 * @property string fee
 * @property string actual_fee
 * @property string profit
 * @property string actual_profit
 * @property int account_mode
 */
class TransactionFee extends Model
{
    protected $primaryKey = null;
    public $incrementing = false;

    const CREATED_AT = null;
    const UPDATED_AT = null;

    protected $fillable = ['transaction_id', 'user_id', 'account_mode', 'thirdchannel_id', 'profit', 'actual_profit', 'fee', 'actual_fee'];

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function creditModeEnabled()
    {
        return $this->account_mode === User::ACCOUNT_MODE_CREDIT;
    }

    public function depositModeEnabled()
    {
        return $this->account_mode === User::ACCOUNT_MODE_DEPOSIT;
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
