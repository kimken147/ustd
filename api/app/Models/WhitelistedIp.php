<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @method static $this|Builder ofIpv4(string $ipv4)
 * @method static $this|Builder ofUser(Authenticatable|User $user)
 * @method static $this|Builder ofType(int $type)
 * @property string ipv4
 * @property int type
 */
class WhitelistedIp extends Model
{

    const TYPE_LOGIN = 1; // 允許登入後臺
    const TYPE_API = 2; // 允許發起 API

    protected $fillable = ['user_id', 'ipv4', 'type'];

    public function setIpv4Attribute($ipv4)
    {
        $this->attributes['ipv4'] = ip2long($ipv4);
    }

    public function getIpv4Attribute()
    {
        return long2ip($this->attributes['ipv4']);
    }

    /**
     * @param  Builder  $builder
     * @param $ipv4
     * @return Builder
     */
    public function scopeOfIpv4(Builder $builder, $ipv4)
    {
        return $builder->where('ipv4', ip2long($ipv4));
    }

    public function scopeOfUser(Builder $builder, User $user)
    {
        return $builder->where('user_id', $user->getKey());
    }

    public function scopeOfType(Builder $builder, int $type)
    {
        return $builder->where('type', $type);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
