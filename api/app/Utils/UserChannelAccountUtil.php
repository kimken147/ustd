<?php


namespace App\Utils;


use App\Models\UserChannelAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserChannelAccountUtil
{
    private $math;

    public function __construct(BCMathUtil $math)
    {
        $this->math = $math;
    }

    public function updateTotal($userChannelAccountId, $amount, $isWithdraw=false)
    {
        $account = UserChannelAccount::find($userChannelAccountId);
        if (empty($account)) {
            return true;
        }

        if ($isWithdraw) {
            $account->withdraw_daily_total = $this->math->add($account->withdraw_daily_total, $amount);
            $account->withdraw_monthly_total =  $this->math->add($account->withdraw_monthly_total, $amount);
        } else {
            $account->daily_total = $this->math->add($account->daily_total, $amount);
            $account->monthly_total =  $this->math->add($account->monthly_total, $amount);
        }
        $account->save();
    }

    public function updatePaymentCount(int $userChannelAccountId, int $count = 1, bool $isWithdraw = false)
    {
        return DB::transaction(function () use ($userChannelAccountId, $count, $isWithdraw) {
            $account = UserChannelAccount::where('id', $userChannelAccountId)
                ->lockForUpdate()
                ->first();

            if (empty($account)) {
                return true;
            }

            if ($isWithdraw) {
                $account->withdraw_daily_transaction_count_total += $count;
                $account->withdraw_monthly_transaction_count_total += $count;
            } else {
                $account->daily_transaction_count_total += $count;
                $account->monthly_transaction_count_total += $count;
            }

            $account->save();
            return true;
        });
    }

    public function rollbackPaymentCount(int $userChannelAccountId, int $count = 1, bool $isWithdraw = false)
    {
        return DB::transaction(function () use ($userChannelAccountId, $count, $isWithdraw) {
            $account = UserChannelAccount::where('id', $userChannelAccountId)
                ->lockForUpdate()
                ->first();

            if (empty($account)) {
                return true;
            }

            if ($isWithdraw) {
                $account->withdraw_daily_transaction_count_total = max(0, $account->withdraw_daily_transaction_count_total - $count);
                $account->withdraw_monthly_transaction_count_total = max(0, $account->withdraw_monthly_transaction_count_total - $count);
            } else {
                $account->daily_transaction_count_total = max(0, $account->daily_transaction_count_total - $count);
                $account->monthly_transaction_count_total = max(0, $account->monthly_transaction_count_total - $count);
            }

            $account->save();
            return true;
        });
    }

    public function updateTotalRollback($userChannelAccountId, $amount, $isWithdraw=false)
    {
        $account = UserChannelAccount::find($userChannelAccountId);
        if (empty($account)) {
            return true;
        }

        if ($isWithdraw) {
            $account->withdraw_daily_total = $this->math->subMinZero($account->withdraw_daily_total, $amount);
            $account->withdraw_monthly_total = $this->math->subMinZero($account->withdraw_monthly_total, $amount);
        } else {
            $account->daily_total = $this->math->subMinZero($account->daily_total, $amount);
            $account->monthly_total = $this->math->subMinZero($account->monthly_total, $amount);
        }

        $account->save();
    }
}
