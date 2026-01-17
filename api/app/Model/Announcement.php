<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string name
 */
class Announcement extends Model
{
    protected $fillable = ['title', 'content', 'notes', 'for_merchant', 'for_provider', 'to_id', 'started_at', 'ended_at'];
    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'for_merchant' => 'boolean',
        'for_provider' => 'boolean'
    ];

    public function users()
    {
        return $this->hasMany(AnnouncementUser::class);
    }
}
