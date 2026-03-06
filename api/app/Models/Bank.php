<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string name
 */
class Bank extends Model
{
    protected $fillable = ['name'];

    protected $casts = [
        'brief_names' => 'array',
        'tags' => 'json'
    ];

}
