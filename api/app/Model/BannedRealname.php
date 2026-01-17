<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class BannedRealname extends Model
{
    const TYPE_TRANSACTION = 1; // 不允许发起交易
    const TYPE_WITHDRAW = 2; // 不允许发起代付

    protected $fillable = ['realname', 'type', 'note'];
    protected $table = 'banned_realnames';
}
