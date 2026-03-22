<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\FeatureToggle;
use App\Utils\BCMathUtil;
use App\Repository\FeatureToggleRepository;
use Illuminate\Support\Facades\DB;

/**
 * @property int user_id
 * @property array detail
 * @property Device device
 * @property ChannelAmount channelAmount
 * @property string account
 * @property int status
 * @property User user
 * @property int wallet_id
 * @property bool time_limit_disabled
 */
class UserChannelAccount extends Model
{

    use SoftDeletes;

    const STATUS_DISABLE = 0; // 強制下線、停用
    const STATUS_ENABLE = 1; // 下線、啟用
    const STATUS_ONLINE = 2; // 上線

    const DAILY_STATUS_DISABLE = 0; //無每日收款限制
    const DAILY_STATUS_ENABLE = 1;

    const MONTHLY_STATUS_DISABLE = 0; //無每月收款限制
    const MONTHLY_STATUS_ENABLE = 1;

    const TYPE_DEPOSIT_WITHDRAW = 1;
    const TYPE_DEPOSIT = 2;
    const TYPE_WITHDRAW = 3;

    const DETAIL_KEY_PROCESSED_QR_CODE_FILE_PATH = 'processed_qr_code_file_path';
    const DETAIL_KEY_QR_CODE_FILE_PATH = 'qr_code_file_path';
    const DETAIL_KEY_REDIRECT_URL = 'redirect_url';
    const DETAIL_KEY_BANK_CARD_HOLDER_NAME = 'bank_card_holder_name';
    const DETAIL_KEY_BANK_CARD_NUMBER = 'bank_card_number';
    const DETAIL_KEY_BANK_CARD_BRANCH = 'bank_card_branch';
    const DETAIL_KEY_BANK_NAME = 'bank_name';
    const DETAIL_KEY_BANK_PROVINCE = 'bank_province';
    const DETAIL_KEY_BANK_CITY = 'bank_city';
    const DETAIL_KEY_ACCOUNT = 'account';
    const DETAIL_KEY_RECEIVER_NAME = 'receiver_name'; // 支付寶轉賬時可能會需要填寫收款人姓名做驗證
    const DETAIL_KEY_REAL_NAME = 'real_name'; // 實名制

    const DETAIL_KEY_CHAIN_NETWORK = 'chain_network';   // 鏈網絡：trc20, erc20, bep20
    const DETAIL_KEY_ENCRYPTED_PRIVATE_KEY = 'encrypted_private_key'; // USDT 出款私鑰（加密儲存）

    const ADDRESS_TYPE_MASTER = 'master';
    const ADDRESS_TYPE_CHILD = 'child';

    // 一次性子地址收款狀態
    const RECEIVE_STATUS_NONE = 'none';
    const RECEIVE_STATUS_UNUSED = 'unused';
    const RECEIVE_STATUS_USED = 'used';

    protected $casts = [
        'regular_customer_first' => 'boolean',
        'time_limit_disabled' => 'boolean',
        'daily_status' => 'boolean',
        'monthly_status' => 'boolean',
        'detail' => 'array',
        'is_auto' => 'boolean',
        'is_one_time' => 'boolean',
        'auto_sync' => 'boolean',
        'onchain_synced_at' => 'datetime',
        'daily_transaction_count_limit' => 'integer',
        'daily_transaction_count_total' => 'integer',
        'withdraw_daily_transaction_count_limit' => 'integer',
        'withdraw_daily_transaction_count_total' => 'integer',
        'monthly_transaction_count_limit' => 'integer',
        'monthly_transaction_count_total' => 'integer',
        'withdraw_monthly_transaction_count_limit' => 'integer',
        'withdraw_monthly_transaction_count_total' => 'integer',
    ];
    protected $dates = [
        'last_matched_at',
    ];
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'channel_code',
        'channel_amount_id',
        'user_id',
        'device_id',
        'wallet_id',
        'bank_id',
        'status',
        'type',
        'min_amount',
        'max_amount',
        'fee_percent',
        'regular_customer_first',
        'account',
        'detail',
        'last_matched_at',
        'daily_status',
        'daily_limit',
        'daily_total',
        'daily_transaction_count_limit',
        'daily_transaction_count_total',
        'withdraw_daily_limit',
        'withdraw_daily_total',
        'withdraw_daily_transaction_count_limit',
        'withdraw_daily_transaction_count_total',
        'monthly_status',
        'monthly_limit',
        'monthly_total',
        'monthly_transaction_count_limit',
        'monthly_transaction_count_total',
        'withdraw_monthly_limit',
        'withdraw_monthly_total',
        'withdraw_monthly_transaction_count_limit',
        'withdraw_monthly_transaction_count_total',
        'balance',
        'balance_limit',
        'onchain_usdt_balance',
        'onchain_native_balance',
        'onchain_synced_at',
        'is_auto',
        'auto_sync',
        'note',
        'single_min_limit',
        'single_max_limit',
        'withdraw_single_min_limit',
        'withdraw_single_max_limit',
        'address_type',
        'parent_account_id',
        'derivation_index',
        'is_one_time',
        'receive_status',
        'linked_transaction_id',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class, 'channel_code');
    }

    public function channelAmount()
    {
        return $this->belongsTo(ChannelAmount::class)->withTrashed();
    }

    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    public function devicePayingTransactions()
    {
        return $this->hasMany(DevicePayingTransaction::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function wallet()
    {
        return $this->belongsTo(Wallet::class);
    }

    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * 母地址關聯（子地址 belongsTo 母地址）
     */
    public function parentAccount()
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    /**
     * 子地址關聯（母地址 hasMany 子地址）
     */
    public function childAccounts()
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    /**
     * 綁定的收款交易（一次性子地址收款後關聯的交易）
     */
    public function linkedTransaction()
    {
        return $this->belongsTo(\App\Models\Transaction::class, 'linked_transaction_id');
    }

    public function transactionGroups()
    {
        return $this->belongsToMany(TransactionGroup::class);
    }

    public function payingDaifu()
    {
        return $this->hasMany(Transaction::class, 'to_channel_account_id')
            ->where('status', Transaction::STATUS_PAYING)
            ->where('transactions.created_at', '>=', now()->subDay());
    }

    public function audits()
    {
        return $this->hasMany(UserChannelAccountAudit::class);
    }

    public function getRestBalance($type = 'deposit')
    {
        $math = new BCMathUtil();
        $featureToggleRepository = app(FeatureToggleRepository::class);

        $dailyLimitId       = FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT;
        $dailyLimitEnabled  = $featureToggleRepository->enabled($dailyLimitId);
        $dailyLimitValue    = $featureToggleRepository->valueOf($dailyLimitId);

        $monthlyLimitId       = FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT;
        $monthlyLimitEnabled  = $featureToggleRepository->enabled($monthlyLimitId);
        $monthlyLimitValue    = $featureToggleRepository->valueOf($monthlyLimitId);

        $restBalance = $this->balance;

        $dailyTotal = ($type == 'deposit') ? $this->daily_total : $this->withdraw_daily_total;
        $dailyLimit = ($type == 'deposit') ? $this->daily_limit : $this->withdraw_daily_limit;

        $monthlyTotal = ($type == 'deposit') ? $this->monthly_total : $this->withdraw_monthly_total;
        $monthlyLimit = ($type == 'deposit') ? $this->monthly_limit : $this->withdraw_monthly_limit;

        if ($dailyLimitEnabled && $this->daily_status && !empty($dailyLimit) && $dailyLimit != 0) {
            $restDailyBalance = $math->subMinZero(($dailyLimit ?? $dailyLimitValue), $dailyTotal);
            $restBalance = min($restBalance, $restDailyBalance);
        }

        if ($monthlyLimitEnabled && $this->monthly_status  && !empty($monthlyLimit) && $monthlyLimit != 0) {
            $restMonthlyBalance = $math->subMinZero(($monthlyLimit ?? $monthlyLimitValue), $monthlyTotal);
            $restBalance = min($restBalance, $restMonthlyBalance);
        }

        return $restBalance;
    }

    public function updateBalanceByTransaction($transaction, $rollback = false)
    {
        $math = app(\App\Utils\BCMathUtil::class);

        DB::beginTransaction();
        try {
            $account = self::lockForUpdate()->find($this->id);
            $oldBalance = $account->balance;
            $newBalance = 0;
            // 收款
            if ($transaction->from_channel_account_id) {
                if ($rollback) {
                    $newBalance = $math->subMinZero($oldBalance, $transaction->floating_amount);
                } else {
                    $newBalance = $math->add($oldBalance, $transaction->floating_amount);
                }
            }

            // 出款
            if ($transaction->to_channel_account_id) {
                if ($rollback) {
                    $newBalance = $math->add($oldBalance, $transaction->floating_amount);
                } else {
                    $newBalance = $math->subMinZero($oldBalance, $transaction->floating_amount);
                }
            }

            if ($oldBalance == $newBalance) {
                DB::commit();
                return false;
            }

            $audit = [
                'old_value' => ['balance' => $oldBalance],
                'new_value' => ['balance' => $newBalance],
                'updated_by_transaction_id' => $transaction->id
            ];

            $account->audits()->create($audit);
            $account->update(['balance' => $newBalance]);
            DB::commit();
        } catch (\Exception $e) {
            \Log::error(__METHOD__, compact('e'));
            DB::rollback();
        }
    }

    public function updateBalanceByUser($value, $type = 'modify', $user = null, $note = '')
    {
        $math = app(BCMathUtil::class);

        DB::beginTransaction();
        try {
            $account = self::lockForUpdate()->find($this->id);

            switch ($type) {
                case 'add':
                    $newBalance = $math->add($account->balance, $value, 2);
                    break;
                case 'minus':
                    $newBalance = $math->sub($account->balance, $value, 2);
                    break;
                case 'modify':
                default:
                    $newBalance = $value;
            }

            if ($account->balance != $newBalance) {
                $userId = isset($user) ? $user->id : 0;
                $audit = [
                    'old_value' => ['balance' => $account->balance],
                    'new_value' => ['balance' => $newBalance],
                    'note' => $note ?? '',
                    'updated_by_user_id' => $userId
                ];

                $account->audits()->create($audit);
                $account->update(['balance' => $newBalance]);
            }

            DB::commit();
        } catch (\Exception $e) {
            \Log::error(__METHOD__, compact('e'));
            DB::rollback();
        }
    }

    /**
     * 查詢可用於歸集出款的一次性子地址（已收款且有餘額）
     */
    public function scopeAvailableForPayout($query, string $minBalance = '0')
    {
        return $query->where('address_type', self::ADDRESS_TYPE_CHILD)
            ->where('receive_status', self::RECEIVE_STATUS_USED)
            ->where('onchain_usdt_balance', '>', $minBalance)
            ->whereNotNull('parent_account_id');
    }

    public function scopeSelectTotalBalance($query)
    {
        return $query->select([
            DB::raw('SUM(balance) AS total_balance'),
            DB::raw('SUM(onchain_usdt_balance) AS total_onchain_usdt_balance'),
            DB::raw('SUM(onchain_native_balance) AS total_onchain_native_balance'),
        ]);
    }
}
