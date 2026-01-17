<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

class MemberDevice extends Model
{
    protected $fillable = [
       'device', 'data'
    ];

    protected $casts = [
        'data' => 'object',
    ];
}
