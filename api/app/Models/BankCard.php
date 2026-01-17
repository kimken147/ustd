<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string bank_name
 * @property string bank_card_number
 * @property string bank_card_holder_name
 */
class BankCard extends Model
{

    const STATUS_REVIEWING = 1;
    const STATUS_REVIEW_PASSED = 2;
    const STATUS_REVIEW_REJECTED = 3;
    const SYSTEM_USER_ID = 0;

    protected $fillable = ['user_id', 'status', 'bank_card_holder_name', 'bank_card_number', 'bank_name',
     'bank_province', 'bank_city'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
