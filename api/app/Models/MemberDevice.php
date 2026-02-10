<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberDevice extends Model
{
    protected $fillable = [
       'device', 'data'
    ];

    protected $casts = [
        'data' => 'object',
    ];
}
