<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BannedIp extends Model
{
    const TYPE_TRANSACTION = 1; // 不允许发起交易

    protected $fillable = ['ipv4', 'type', 'note'];
    protected $table = 'banned_ips';

    public function setIpv4Attribute($ipv4)
    {
        $this->attributes['ipv4'] = ip2long($ipv4);
    }

    public function getIpv4Attribute()
    {
        return long2ip($this->attributes['ipv4']);
    }
}
