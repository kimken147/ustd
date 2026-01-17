<?php

namespace App\Model;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Kalnoy\Nestedset\NodeTrait;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * @property int id
 * @property int role
 * @property string name
 * @property string username
 * @property Carbon|null last_login_at
 * @property string|null last_login_ipv4
 * @property string secret_key
 * @property string contact
 * @property User|null parent
 * @property Wallet|null wallet
 * @property Device[] devices
 * @property boolean god
 * @property boolean deposit_enable
 * @property boolean paufen_deposit_enable
 * @property boolean withdraw_enable
 * @property boolean withdraw_google2fa_enable
 * @property boolean paufen_withdraw_enable
 * @property boolean transaction_enable
 * @property boolean third_channel_enable
 * @property int status
 * @property Collection permissions
 * @property Collection userChannels
 * @property string phone
 * @property int account_mode
 * @property boolean balance_transfer_enable
 * @property boolean agency_withdraw_enable
 * @property boolean paufen_agency_withdraw_enable
 * @property boolean withdraw_review_enable
 * @property boolean exchange_mode_enable
 * @property FakeCryptoWallet|null fakeUsdtCryptoWallet
 */
class User extends Authenticatable implements JWTSubject
{

    use Notifiable, NodeTrait, SoftDeletes;

    const ROLE_ADMIN = 1;

    const ROLE_PROVIDER = 2;

    const ROLE_MERCHANT = 3;

    const ROLE_SUB_ACCOUNT = 4;

    const ROLE_MERCHANT_SUB_ACCOUNT = 5;

    const STATUS_DISABLE = 0;

    const STATUS_ENABLE = 1;

    const ACCOUNT_MODE_GENERAL = 1; // 一般模式
    const ACCOUNT_MODE_CREDIT = 2; // 信用模式
    const ACCOUNT_MODE_DEPOSIT = 3; // 押金模式

    protected $casts = [
        'role'                          => 'integer',
        'god'                           => 'boolean',
        'agent_enable'                  => 'boolean',
        'google2fa_enable'              => 'boolean',
        'deposit_enable'                => 'boolean',
        'paufen_deposit_enable'         => 'boolean',
        'withdraw_review_enable'        => 'boolean',
        'withdraw_enable'               => 'boolean',
        'withdraw_profit_enable'        => 'boolean',
        'withdraw_google2fa_enable'     => 'boolean',
        'paufen_withdraw_enable'        => 'boolean',
        'transaction_enable'            => 'boolean',
        'third_channel_enable'          => 'boolean',
        'ready_for_matching'            => 'boolean',
        'account_mode'                  => 'integer',
        'balance_transfer_enable'       => 'boolean',
        'agency_withdraw_enable'        => 'boolean',
        'paufen_agency_withdraw_enable' => 'boolean',
        'exchange_mode_enable'          => 'boolean',
        'cancel_order_enable'           => 'boolean',
        'control_downline'              => 'boolean',
    ];
    /**
     * @var User
     */
    public $currentSubAccount;
    protected $dates = ['last_login_at', 'last_activity_at', 'last_matched_at'];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'role',
        'username',
        'status',
        'name',
        'email',
        'password',
        'last_login_ipv4',
        'last_login_at',
        'last_login_city',
        'agent_enable',
        'google2fa_enable',
        'google2fa_secret',
        'secret_key',
        'password',
        'parent_id',
        'deposit_enable',
        'withdraw_enable',
        'withdraw_profit_enable',
        'withdraw_google2fa_enable',
        'transaction_enable',
        'contact',
        'phone',
        'account_mode',
        'ready_for_matching',
        'last_activity_at',
        'last_matched_at',
        'god',
        'paufen_deposit_enable',
        'paufen_withdraw_enable',
        'balance_transfer_enable',
        'agency_withdraw_enable',
        'paufen_agency_withdraw_enable',
        'withdraw_review_enable',
        'exchange_mode_enable',
        'control_downline',
        'token',
        'usdt_rate',
        'third_channel_enable',
        'include_self_providers',
        'balance_limit',
        'cancel_order_enable',
        'currency',
        'wallet_id'
    ];
    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'google2fa_secret',
        'secret_key',
    ];

    public function creditModeEnabled()
    {
        return $this->account_mode === self::ACCOUNT_MODE_CREDIT;
    }

    public function depositModeEnabled()
    {
        return $this->account_mode === self::ACCOUNT_MODE_DEPOSIT;
    }

    public function deposits()
    {
        return $this->hasMany(Transaction::class, 'to_id');
    }

    public function successDeposits()
    {
        return $this->hasMany(Transaction::class, 'to_id')
            ->whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT])
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    }

    public function todaySuccessPaufenTransactions()
    {
        return $this->hasMany(Transaction::class, 'from_id')
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->where('confirmed_at', '>=', now()->startOfDay())
            ->where('confirmed_at', '<=', now()->endOfDay());
    }

    public function successPaufenTransactions()
    {
        return $this->hasMany(Transaction::class, 'from_id')
            ->where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    }

    public function todaySuccessWithdraws()
    {
        return $this->hasMany(Transaction::class, 'to_id')
            ->whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
            ->where('confirmed_at', '>=', now()->startOfDay())
            ->where('confirmed_at', '<=', now()->endOfDay());
    }

    public function successWithdraws()
    {
        return $this->hasMany(Transaction::class, 'from_id')
            ->whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])
            ->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    }

    public function profits()
    {
        return $this->hasMany(TransactionFee::class, 'user_id')
            ->where('actual_profit', '>',  0);
    }

    public function devices()
    {
        return $this->hasMany(Device::class);
    }

    public function disabled()
    {
        return $this->status === User::STATUS_DISABLE;
    }

    /**
     * @inheritDoc
     */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getLastLoginIpv4Attribute($value)
    {
        if (empty($value)) {
            return null;
        }

        return long2ip($value);
    }

    public function isAdmin()
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isSubAccount()
    {
        return in_array($this->role, [self::ROLE_SUB_ACCOUNT, self::ROLE_MERCHANT_SUB_ACCOUNT]);
    }

    public function mainUser()
    {
        if (in_array($this->role, [self::ROLE_SUB_ACCOUNT, self::ROLE_MERCHANT_SUB_ACCOUNT])) {
            return $this->parent;
        }

        return $this;
    }

    public function matchingDepositGroups()
    {
        return $this->hasMany(TransactionGroup::class, 'owner_id')->where(
            'transaction_type',
            Transaction::TYPE_PAUFEN_WITHDRAW
        );
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class)->withTimestamps();
    }

    public function realUser()
    {
        if ($this->currentSubAccount) {
            return $this->currentSubAccount;
        }

        return $this;
    }

    public function rootParents()
    {
        return $this->ancestors()->where('parent_id', null);
    }

    public function scopeOfRole($builder, $role)
    {
        if (is_array($role)) {
            return $builder->whereIn('role', $role);
        }

        return $builder->where('role', $role);
    }

    public function setLastLoginIpv4Attribute($value)
    {
        $this->attributes['last_login_ipv4'] = ip2long($value);
    }

    public function systemBankCards()
    {
        return $this->belongsToMany(SystemBankCard::class)->withPivot(['share_descendants'])->withTimestamps();
    }

    public function transactionGroups()
    {
        return $this->hasMany(TransactionGroup::class, 'owner_id')->where(
            'transaction_type',
            Transaction::TYPE_PAUFEN_TRANSACTION
        );
    }

    public function thirdChannels()
    {
        return $this->hasMany(MerchantThirdChannel::class, 'owner_id');
    }

    public function userChannelAccounts()
    {
        return $this->hasMany(UserChannelAccount::class);
    }

    public function userChannels()
    {
        return $this->hasMany(UserChannel::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function walletHistories()
    {
        return $this->hasMany(WalletHistory::class);
    }

    public function whitelistedIps()
    {
        return $this->hasMany(WhitelistedIp::class);
    }

    public function fakeCryptoWallets()
    {
        return $this->hasMany(FakeCryptoWallet::class);
    }

    public function fakeUsdtCryptoWallet()
    {
        return $this->hasOne(FakeCryptoWallet::class)->where('currency', FakeCryptoWallet::CURRENCY_USDT);
    }

    public function getDescendantsId($includeSelf = true)
    {
        $ids = $this->descendants()->pluck('id');

        if ($includeSelf) {
            $ids[] = $this->getKey();
        }

        return $ids;
    }

    public function controlDownlines()
    {
        return $this->belongsToMany(User::class, 'control_downlines', 'parent_id', 'downline_id');
    }

    public function canControl($user)
    {
        $isSelfOrDescendantOf = $user->isSelfOrDescendantOf($this);

        return $isSelfOrDescendantOf ||
            ($this->control_downline && $this->controlDownlines->contains(function ($value) use ($user) {
                return $value->id == $user->id;
            }));
    }

    public function isProvider()
    {
        return $this->role == self::ROLE_PROVIDER;
    }

    public function fromSelfMessages()
    {
        return $this->hasMany(Message::class, 'from_id');
    }

    public function toSelfMessages()
    {
        return $this->hasMany(Message::class, 'to_id');
    }

    public function unreadMessages($fromId)
    {
        return $this->toSelfMessages->where('from_id', $fromId)->whereNull('readed_at');
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'user_tags')
            ->select(['tags.id', 'tags.name'])
            ->withPivot([]) // 空陣列表示不取得任何 pivot 欄位
            ->withTimestamps(false); // 不需要
    }

    // 新增標籤的方法
    public function addTags($tagIds)
    {
        return $this->tags()->attach($tagIds);
    }

    // 移除標籤的方法
    public function removeTags($tagIds)
    {
        return $this->tags()->detach($tagIds);
    }

    // 同步標籤的方法（會清除未包含在陣列中的標籤）
    public function syncTags($tagIds)
    {
        return $this->tags()->sync($tagIds);
    }
}
