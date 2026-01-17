<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class FakeCryptoTransaction extends Model
{
    protected $fillable = ['transaction_id', 'amount', 'currency'];
}
