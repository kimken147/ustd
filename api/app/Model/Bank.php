<?php

namespace App\Model;

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

    public function getNeedExtraWithdrawFeeAttribute()
    {
        if (!isset($this->tags) || empty($this->tags))  {
            return false;
        }
        return in_array('extra_withdraw_fee', $this->tags);
    }

    public function getExtraWithdrawFeeAttribute()
    {
        if (!isset($this->tags) || empty($this->tags))  {
            return 0;
        }

        return in_array('extra_withdraw_fee', $this->tags) ? 15 : 0;
    }
}
