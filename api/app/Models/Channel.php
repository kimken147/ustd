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

    const CODE_ALIPAY_BANK = 'ALIPAY_BANK'; // 支轉卡在某些地方需要特殊處理，所以加上這個常數
    const CODE_BANK_CARD = 'BANK_CARD';
    const CODE_QR_ALIPAY = 'QR_ALIPAY';
    const CODE_ECNY = 'ECNY';
    const CODE_O_ALIPAY = "O_ALIPAY";
    const CODE_ZH_ALIPAY = 'ZH_ALIPAY';
    const CODE_RE_ALIPAY = 'RE_ALIPAY'; // 口令紅包
    const CODE_UNION_QUICK_PASS = "UNION_QUICK_PASS";
    const CODE_UNION_QR = "UNION_QR";
    const CODE_UNION_H5 = "UNION_H5";
    const CODE_RE_QQ = 'RE_QQ'; // 面对面红包
    const CODE_QR_YFB = 'QR_YFB'; // 易付寶
    const CODE_QR_WECHATPAY = 'QR_WECHATPAY';
    const CODE_WECHATPAY_H5 = 'WECHATPAY_H5';
    const CODE_WEHCHATPAY_O = 'WECHATPAY_O';
    const CODE_WECHATPAY_BAC = 'QR_WECHATPAY_BAC';
    const CODE_WECHATPAY_SAC = 'QR_WECHATPAY_SAC';
    const CODE_RE_DINGDING = 'RE_DINGDING';
    const CODE_QR_QQ = 'QR_QQ';
    const CODE_USDT = 'USDT';
    const CODE_PHONE_H5 = 'PHONE_H5';
    const CODE_ALIPAY_H5 = 'ALIPAY_H5';
    const CODE_ALIPAY_SAC = 'ALIPAY_SAC';
    const CODE_ALIPAY_BAC = 'ALIPAY_BAC';
    const CODE_ALIPAY_GC = 'ALIPAY_GC';
    const CODE_ALIPAY_VM = 'ALIPAY_VM';
    const CODE_ALIPAY_COPY = 'ALIPAY_COPY';
    const CODE_CRYSTAL_CARD = 'CRYSTAL_CARD';
    const CODE_ELITE_CARD = 'ELITE_CARD';

    // 菲律賓通道
    const CODE_GCASH = 'GCASH';
    const CODE_QR_GCASH = 'GCASH_QR';
    const CODE_MAYA = "MAYA";

    // 越南通道
    const CODE_QR_MOMOPAY = 'QR_MOMOPAY';
    const CODE_QR_BANK = 'QR_BANK';
    const CODE_QR_ACB = 'QR_ACB';
    const CODE_QR_AGR = 'QR_AGR';
    const CODE_QR_BIDV = 'QR_BIDV';
    const CODE_QR_EIB = 'QR_EIB';
    const CODE_QR_MB = 'QR_MB';
    const CODE_QR_MSB = 'QR_MSB';
    const CODE_QR_TCB = 'QR_TCB';
    const CODE_QR_TPB = 'QR_TPB';
    const CODE_QR_VCB = 'QR_VCB';
    const CODE_QR_VIB = 'QR_VIB';
    const CODE_QR_VPB = 'QR_VPB';
    const CODE_QR_VTB = 'QR_VTB';

    const CODE_DC_ACB = 'DC_ACB';
    const CODE_DC_BIDV = 'DC_BIDV';
    const CODE_DC_EIB = 'DC_EIB';
    const CODE_DC_MB = 'DC_MB';
    const CODE_DC_STB = 'DC_STB';
    const CODE_DC_TCB = 'DC_TCB';
    const CODE_DC_TPB = 'DC_TPB';
    const CODE_DC_VCB = 'DC_VCB';
    const CODE_DC_VTB = 'DC_VTB';
    const CODE_DC_BANK = 'DC_BANK';

    const STATUS_DISABLE = 0;
    const STATUS_ENABLE = 1;

    const RESPONSE_QRCODE = 1;
    const RESPONSE_URL = 2;
    const RESPONSE_BANK_CARD = 3;
    const RESPONSE_FORM = 4;
    const RESPONSE_GCASH = 5;

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

    public function channelAmounts()
    {
        return $this->hasMany(ChannelAmount::class);
    }

    public function channelGroups()
    {
        return $this->hasMany(ChannelGroup::class);
    }

    public function scanQrcodeUrlScheme()
    {
        if ($this->code == self::CODE_QR_ALIPAY || $this->code == self::CODE_ALIPAY_SAC || $this->code == self::CODE_ALIPAY_BAC || $this->code === self::CODE_ALIPAY_GC) {
            return 'https://ds.alipay.com/?from=mobilecodec&scheme=alipays%3A%2F%2Fplatformapi%2Fstartapp%3FsaId%3D10000007';
        } elseif ($this->code == 'QR_YFB') {
            return '';
        } elseif ($this->code == 'QR_WECHATPAY') {
            return '';
        } else {
            return '';
        }
    }
}
