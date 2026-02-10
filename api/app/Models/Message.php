<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
/**
 * @property string name
 */
class Message extends Model
{
    const TYPE_TEXT = 1;
    const TYPE_FILE = 2;

    protected $fillable = ['from_id', 'to_id', 'text', 'readed_at', 'type', 'detail'];
    protected $casts = [
        'detail' => 'array',
        'readed_at' => 'datetime'
    ];

    public function from()
    {
        return $this->belongsTo(User::class);
    }

    public function to()
    {
        return $this->belongsTo(User::class);
    }

}
