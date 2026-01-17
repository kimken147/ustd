<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int status
 * @property string balance
 * @property string bank_card_holder_name
 * @property string bank_card_number
 * @property string bank_name
 * @property string note
 * @property Carbon created_at
 * @property Carbon updated_at
 * @property Carbon|null published_at
 * @property Carbon|null last_matched_at
 */
class SystemBankCard extends Model
{

    const STATUS_UNPUBLISHED = 0;
    const STATUS_PUBLISHED = 1;
    protected $dates = [
        'published_at', 'last_matched_at',
    ];
    protected $fillable = [
        'status',
        'balance',
        'bank_card_holder_name',
        'bank_card_number',
        'bank_name',
        'bank_province',
        'bank_city',
        "note",
        'created_at',
        'updated_at',
        'published_at',
        'last_matched_at',
    ];

    public function scopeHasSufficientBalance(Builder $builder, $amount)
    {
        return $builder->where('balance', '>=', $amount);
    }

    public function scopeOldestMatched(Builder $builder)
    {
        return $builder->oldest('last_matched_at');
    }

    public function scopePublished(Builder $builder)
    {
        return $builder->whereNotNull('published_at')->where('status', self::STATUS_PUBLISHED);
    }

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['share_descendants'])->withTimestamps();
    }

    public function shareDescendantsUsers()
    {
        return $this->belongsToMany(User::class)->withPivot(['share_descendants'])->withTimestamps()->wherePivot('share_descendants', true);
    }

    public function nonShareDescendantsUsers()
    {
        return $this->belongsToMany(User::class)->withPivot(['share_descendants'])->withTimestamps()->wherePivot('share_descendants', false);
    }
}
