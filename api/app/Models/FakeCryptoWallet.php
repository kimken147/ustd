<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FakeCryptoWallet extends Model
{
    const CURRENCY_BTC = 1;
    const CURRENCY_ETH = 2;
    const CURRENCY_USDT = 3;

    protected $fillable = ['user_id', 'currency', 'balance'];
}
