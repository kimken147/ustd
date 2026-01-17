<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Model\FeatureToggle;
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
    const DETAIL_KEY_BANK_ID = 'bank_id';
    const DETAIL_KEY_ACCOUNT = 'account';
    const DETAIL_KEY_RECEIVER_NAME = 'receiver_name'; // 支付寶轉賬時可能會需要填寫收款人姓名做驗證
    const DETAIL_KEY_ALIPAY_BANK_CODE = 'alipay_bank_code'; // 專門給支轉銀用的支付寶銀行代碼（透過支付寶 API 查詢而來）
    const DETAIL_KEY_REAL_NAME = 'real_name'; // 實名制
    protected $casts = [
        'regular_customer_first' => 'boolean',
        'time_limit_disabled' => 'boolean',
        'daily_status' => 'boolean',
        'monthly_status' => 'boolean',
        'detail' => 'array',
        'is_auto' => 'boolean',
        'auto_sync' => 'boolean'
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
        'withdraw_daily_limit',
        'withdraw_daily_total',
        'monthly_status',
        'monthly_limit',
        'monthly_total',
        'withdraw_monthly_limit',
        'withdraw_monthly_total',
        'balance',
        'balance_limit',
        'is_auto',
        'auto_sync',
        'note',
        'single_min_limit',
        'single_max_limit',
        'withdraw_single_min_limit',
        'withdraw_single_max_limit',
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
                $amount = $math->add($transaction->floating_amount, data_get($transaction->from_channel_account, 'extra_withdraw_fee', 0));
                if ($rollback) {
                    $newBalance = $math->add($oldBalance, $amount);
                } else {
                    $newBalance = $math->subMinZero($oldBalance, $amount);
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
}
