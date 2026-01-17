<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class TransactionCertificateFile extends Model
{
    protected $fillable = ['transaction_id', 'path'];
}
