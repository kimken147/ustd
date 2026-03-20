<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property string code
 * @property string name
 * @property int order_timeout
 * @property bool order_timeout_enable
 * @property int transaction_timeout
 * @property bool transaction_timeout_enable
 * @property int present_result
 * @property Collection channelGroups
 * @property bool real_name_enable
 * @property bool note_enable
 * @property bool max_one_ignore_amount
 * @property bool floating_enable
 * @property string floating
 */
class Channel extends Model
{

    const CODE_USDT = 'USDT';
    const CODE_USDT_TRC20 = 'USDT_TRC20';
    const CODE_USDT_ERC20 = 'USDT_ERC20';
    const CODE_USDT_BEP20 = 'USDT_BEP20';

    const USDT_CODES = [
        self::CODE_USDT_TRC20,
        self::CODE_USDT_ERC20,
        self::CODE_USDT_BEP20,
    ];

    const USDT_CHAIN_MAP = [
        self::CODE_USDT_TRC20 => 'trc20',
        self::CODE_USDT_ERC20 => 'erc20',
        self::CODE_USDT_BEP20 => 'bep20',
    ];

    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;

    const RESPONSE_QRCODE = 1;
    const RESPONSE_URL = 2;
    const RESPONSE_BANK_CARD = 3;
    const RESPONSE_FORM = 4;

    const NOTE_GROCERIES = 1;
    const NOTE_TREASURE = 2;

    const TYPE_DEPOSIT_WITHDRAW = 1;
    const TYPE_DEPOSIT_ONLY = 2;
    const TYPE_WITHDRAW_ONLY = 3;

    protected $casts = [
        'order_timeout_enable'       => 'boolean',
        'transaction_timeout_enable' => 'boolean',
        'floating_enable'            => 'boolean',
        'real_name_enable'           => 'boolean',
        'note_enable'                => 'boolean',
        'max_one_ignore_amount'      => 'boolean',
        'geolocation_match'          => 'boolean',
        'deposit_account_fields'     => 'json',
        'withdraw_account_fields'    => 'json'
    ];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'code',
        'name',
        'status',
        'type',
        'order_timeout',
        'order_timeout_enable',
        'transaction_timeout',
        'transaction_timeout_enable',
        'floating',
        'floating_enable',
        'present_result',
        'real_name_enable',
        'note_enable',
        'note_type',
        'max_one_ignore_amount',
        'geolocation_match',
        'deposit_account_fields',
        'withdraw_account_fields',
        "third_exclusive_enable"
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [];
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'code';
    protected $table = 'channels';

    public static function isUsdt(string $code): bool
    {
        return in_array($code, self::USDT_CODES, true);
    }

    public static function chainNetwork(string $code): ?string
    {
        return self::USDT_CHAIN_MAP[$code] ?? null;
    }

    public function channelAmounts()
    {
        return $this->hasMany(ChannelAmount::class);
    }

    public function channelGroups()
    {
        return $this->hasMany(ChannelGroup::class);
    }

}
