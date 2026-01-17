<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string name
 */
class AnnouncementUser extends Model
{
    protected $table = 'announcement_users';

    protected $fillable = ['announcement_id', 'user_id'];

    public $timestamps = false;
}
