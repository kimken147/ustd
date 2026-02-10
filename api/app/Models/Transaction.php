<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

use App\Utils\BCMathUtil;

class Transaction extends Model
{
    const TYPE_PAUFEN_TRANSACTION = 1; // 跑分交易
    const TYPE_PAUFEN_WITHDRAW = 2; // 跑分提現、跑分充值
    const TYPE_NORMAL_DEPOSIT = 3; // 一般充值
    const TYPE_NORMAL_WITHDRAW = 4; // 一般提現
    const TYPE_INTERNAL_TRANSFER = 5; // 内部轉帳
    const TYPE_VIRTUAL_PAUFEN_WITHDRAW_AVAILABLE_FOR_ADMIN = 201; // 管理用虛擬狀態：跑分提現（可鎖定）

    const SUB_TYPE_WITHDRAW = 1; // 下發
    const SUB_TYPE_AGENCY_WITHDRAW = 2; // 代付
    const SUB_TYPE_WITHDRAW_PROFIT = 3; // 紅利提現

    const STATUS_PENDING_REVIEW = 1; // 審核中
    const STATUS_REVIEW_PASSED = 101; // 虛擬狀態，提供前端用，意指審核通過，審核通過時其實是會依照類型轉變為 PAYING 或 MATCHING 等等。
    const STATUS_MATCHING = 2; // 匹配中
    const STATUS_PAYING = 3; // 等待付款
    const STATUS_SUCCESS = 4; // 成功
    const STATUS_MANUAL_SUCCESS = 5; // 手動成功
    const STATUS_MATCHING_TIMED_OUT = 6; // 匹配超時
    const STATUS_PAYING_TIMED_OUT = 7; // 支付超時
    const STATUS_FAILED = 8; // 失敗
    const STATUS_PAYED = 9; // 已支付（付款方已確認支付）
    const STATUS_RECEIVED = 10; // 已收款（收款方已確認收款）
    const STATUS_THIRD_PAYING = 11; // 三方進行中

    const NOTIFY_STATUS_NONE = 0;
    const NOTIFY_STATUS_PENDING = 1;
    const NOTIFY_STATUS_SENDING = 2;
    const NOTIFY_STATUS_SUCCESS = 3;
    const NOTIFY_STATUS_FAILED = 4;

    protected $casts = [
        'type'                 => 'integer',
        'from_channel_account' => 'json',
        'to_channel_account'   => 'json',
        'to_wallet_settled'    => 'boolean',
    ];
    protected $dates = [
        'matched_at',
        'confirmed_at',
        'notified_at',
        'locked_at',
        'operated_at',
        'refunded_at',
        'should_refund_at',
        'to_wallet_should_settled_at',
    ];
    protected $fillable = [
        'parent_id',
        'from_id',
        'from_wallet_id',
        'to_id',
        'to_wallet_id',
        'locked_by_id',
        'refunded_by_id',
        'operator_id',
        'client_ipv4',
        'type',
        'sub_type',
        'status',
        'notify_status',
        'deduct_frozen_balance',
        'from_account_mode',
        'to_account_mode',
        'from_channel_account',
        'to_channel_account',
        'amount',
        'floating_amount',
        'actual_amount',
        'usdt_rate',
        'channel_code',
        'from_channel_account_hash_id',
        'note',
        'bug_report',
        'certificate_file_path',
        'order_number',
        'system_order_number',
        'notify_url',
        'from_device_name',
        'matched_at',
        'locked_at',
        'confirmed_at',
        'operated_at',
        'refunded_at',
        'should_refund_at',
        'mall_sync_at',
        'thirdchannel_id',
        'from_channel_account_id',
        'to_channel_account_id',
        '_from_channel_account',
        '_search1',
        '_search2'
    ];

    public static function boot()
    {
        parent::boot();

        static::created(function ($transaction) {
            $data = [
                'system_order_number' => config('transaction.system_order_number_prefix') . now()->format('YmdHis') . $transaction->id
            ];

            if (!$transaction->order_number) {
                $data['order_number'] = $data['system_order_number'];
            }

            $transaction->update($data);
        });
    }

    public function channel()
    {
        return $this->hasOne(Channel::class, 'code', 'channel_code');
    }

    public function child()
    {
        return $this->hasOne(Transaction::class, 'parent_id', 'id');
    }

    public function children()
    {
        return $this->hasMany(Transaction::class, 'parent_id', 'id');
    }

    public function isRoot()
    {
        return empty($this->parent_id);
    }

    public function from()
    {
        return $this->belongsTo(User::class, 'from_id')->withTrashed();
    }

    public function fromWallet()
    {
        return $this->belongsTo(Wallet::class, 'from_wallet_id', 'id');
    }

    public function getClientIpv4Attribute($value)
    {
        if (empty($value)) {
            return null;
        }

        return long2ip($value);
    }

    public function getLockedAttribute()
    {
        return !is_null($this->locked_at);
    }

    public function getRateAmountAttribute()
    {
        $math = new BCMathUtil();
        return $math->mul($this->amount, $this->usdt_rate, 2);
    }

    public function isPaufenTransaction()
    {
        return $this->type === self::TYPE_PAUFEN_TRANSACTION;
    }

    public function isSuccessful()
    {
        return in_array($this->status, [self::STATUS_SUCCESS, self::STATUS_MANUAL_SUCCESS]);
    }

    public function lockedBy()
    {
        return $this->belongsTo(User::class);
    }

    public function refundedBy()
    {
        return $this->belongsTo(User::class);
    }

    public function matching()
    {
        return (int) $this->status === self::STATUS_MATCHING;
    }

    public function matchingTimedOut()
    {
        return $this->status === self::STATUS_MATCHING_TIMED_OUT;
    }

    public function operator()
    {
        return $this->belongsTo(User::class);
    }

    public function parent()
    {
        return $this->belongsTo(Transaction::class, 'parent_id', 'id');
    }

    public function paying()
    {
        return $this->status === self::STATUS_PAYING || $this->status === self::STATUS_THIRD_PAYING;
    }

    public function payingTimedOut()
    {
        return $this->status === self::STATUS_PAYING_TIMED_OUT;
    }

    public function refundYet()
    {
        return !$this->refunded_at && !$this->refunded_by_id && $this->from_id;
    }

    public function thirdChannelPaying()
    {
        return $this->status === self::STATUS_THIRD_PAYING || $this->thirdchannel_id;
    }

    public function setClientIpv4Attribute($value)
    {
        if (empty($value)) {
            $this->attributes['client_ipv4'] = null;

            return;
        }

        $this->attributes['client_ipv4'] = ip2long($value);
    }

    public function shouldMatchingTimedOut()
    {
        return $this->channel->order_timeout_enable && (now()->diffInSeconds($this->created_at) >= $this->channel->order_timeout);
    }

    public function success()
    {
        return in_array($this->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS]);
    }

    public function failed()
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function to()
    {
        return $this->belongsTo(User::class, 'to_id')->withTrashed();
    }

    public function toWallet()
    {
        return $this->belongsTo(Wallet::class, 'to_wallet_id', 'id');
    }

    public function toWalletShouldSettledNow()
    {
        if (!in_array($this->status, [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])) {
            return false;
        }

        // 確認是否曾結算過
        if ($this->to_wallet_settled) {
            return false;
        }

        // 可以立即結算
        if (!$this->to_wallet_should_settled_at) {
            return true;
        }

        return now()->gte($this->to_wallet_should_settled_at);
    }

    public function transactionFees()
    {
        return $this->hasMany(TransactionFee::class);
    }

    public function transactionNotes()
    {
        return $this->hasMany(TransactionNote::class);
    }

    public function thirdChannel()
    {
        return $this->belongsTo(ThirdChannel::class, 'thirdchannel_id');
    }

    public function isChild()
    {
        return !empty($this->parent_id);
    }

    public function isWithdrawSeparatedChild()
    {
        return $this->isWithdraw() && $this->isChild();
    }

    public function isParent()
    {
        return empty($this->parent_id);
    }

    public function isWithdrawSeparated()
    {
        return $this->isWithdraw() && $this->children()->exists();
    }

    public function isWithdraw()
    {
        return in_array($this->type, [self::TYPE_PAUFEN_WITHDRAW, self::TYPE_NORMAL_WITHDRAW]);
    }

    public function isInternalTransfer()
    {
        return in_array($this->type, [self::TYPE_INTERNAL_TRANSFER]);
    }

    public function isWithdrawAndDeposit()
    {
        return in_array($this->type, [self::TYPE_PAUFEN_WITHDRAW, self::TYPE_NORMAL_DEPOSIT, self::TYPE_NORMAL_WITHDRAW]);
    }

    public function siblings()
    {
        return $this->hasMany(Transaction::class, 'parent_id', 'parent_id');
    }

    public function certificateFiles()
    {
        return $this->hasMany(TransactionCertificateFile::class)->orderBy('id');
    }

    public function fakeCryptoTransaction()
    {
        return $this->hasOne(FakeCryptoTransaction::class);
    }

    public function fromChannelAccount()
    {
        return $this->belongsTo(UserChannelAccount::class, 'from_channel_account_id', 'id');
    }

    public function toChannelAccount()
    {
        return $this->belongsTo(UserChannelAccount::class, 'to_channel_account_id', 'id');
    }

    public function scopeUseIndex($query, $index)
    {
        $table = $this->getTable();
        return $query->from(DB::raw("`$table` USE INDEX(`$index`)"));
    }
}
