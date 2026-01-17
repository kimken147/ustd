<?php


namespace App\Utils;


use App\Model\UserChannelAccount;
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
