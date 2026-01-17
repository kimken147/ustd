<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int transaction_id
 * @property int user_id
 * @property User|null user
 * @property string note
 */
class TransactionNote extends Model
{
    protected $fillable = ['transaction_id', 'user_id', 'note'];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
