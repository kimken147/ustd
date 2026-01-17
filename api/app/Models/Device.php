<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property User user
 * @property string name
 * @property Carbon|null last_login_at
 * @property string last_login_ipv4
 * @property Carbon|null last_heartbeat_at
 * @property int user_id
 * @property boolean regular_customer_first
 */
class Device extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id', 'regular_customer_first', 'name', 'last_login_at', 'last_heartbeat_at', 'last_login_ipv4'
    ];

    protected $dates = [
        'last_login_at',
        'last_heartbeat_at',
    ];

    protected $casts = [
        'regular_customer_first' => 'bool',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function setLastLoginIpv4Attribute($value)
    {
        $this->attributes['last_login_ipv4'] = ip2long($value);
    }

    public function getLastLoginIpv4Attribute($value)
    {
        if (empty($value)) {
            return null;
        }

        return long2ip($value);
    }

    public function userChannelAccounts()
    {
        return $this->hasMany(UserChannelAccount::class);
    }

    public function deviceRegularCustomers()
    {
        return $this->hasMany(DeviceRegularCustomer::class);
    }

    public function devicePayingTransactions()
    {
        return $this->hasMany(DevicePayingTransaction::class);
    }
}
