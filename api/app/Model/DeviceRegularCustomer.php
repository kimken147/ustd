<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class DeviceRegularCustomer extends Model
{
    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = ['device_id', 'client_ipv4'];

    public function setClientIpv4Attribute($value)
    {
        $this->attributes['client_ipv4'] = ip2long($value);
    }

    public function getClientIpv4Attribute($value)
    {
        if (empty($value)) {
            return null;
        }

        return long2ip($value);
    }
}
