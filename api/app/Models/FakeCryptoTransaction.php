<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FakeCryptoTransaction extends Model
{
    protected $fillable = ['transaction_id', 'amount', 'currency'];
}
