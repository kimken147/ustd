<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OnlineReadyForMatchingUserCollection;
use App\Models\Transaction;
use App\Models\User;
use App\Models\FeatureToggle;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OnlineReadyForMatchingUserController extends Controller
{

    public function index(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        $this->validate($request, [
            'channel_code' => 'required|array',
        ]);

            $onlineUsers = UserChannelAccount::where('status', UserChannelAccount::STATUS_ONLINE)
            ->whereIn('type', [UserChannelAccount::TYPE_DEPOSIT_WITHDRAW, UserChannelAccount::TYPE_DEPOSIT, UserChannelAccount::TYPE_WITHDRAW])
            ->whereHas('user', function ($query) use ($request, $featureToggleRepository) {
                $query->where([
                    ['transaction_enable', true],
                    ['role', User::ROLE_PROVIDER],
                ])
                ->when(!$featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM), function (Builder $builder) {
                    $builder->where('ready_for_matching', true);
                });
            })
            ->leftJoinSub(
                Transaction::select([
                    'from_channel_account_id',
                    DB::raw('COUNT(id) AS total_paying_count'),
                    DB::raw('SUM(floating_amount) AS total_paying_balance'),
                ])->where([
                    ['type', Transaction::TYPE_PAUFEN_TRANSACTION],
                    ['status', Transaction::STATUS_PAYING],
                    ['created_at', '>=', now()->subMinutes(30)],
                ])->whereIn('channel_code', $request->input('channel_code'))
                ->groupBy('from_channel_account_id'),
                'paying_transactions',
                'paying_transactions.from_channel_account_id',
                '=',
                'user_channel_accounts.id'
            )->leftJoinSub(
                Transaction::select([
                    'to_channel_account_id',
                    DB::raw('COUNT(id) AS total_withdraw_count'),
                    DB::raw('SUM(floating_amount) AS total_withdraw_balance'),
                ])->where([
                    ['type', Transaction::TYPE_PAUFEN_WITHDRAW],
                    ['status', Transaction::STATUS_PAYING]
                ])
                ->groupBy('to_channel_account_id'),
                'withdraw_transactions',
                'withdraw_transactions.to_channel_account_id',
                '=',
                'user_channel_accounts.id'
            )
            ->whereHas('channelAmount', function ($query) use ($request) {
                $query->whereIn('channel_code', $request->input('channel_code'));
            })
            ->with([
                'wallet', 'channelAmount.channel', 'device'
            ])
            ->get();

            $dailyLimitId       = FeatureToggle::USER_CHANNEL_ACCOUNT_DAILY_LIMIT;
            $dailyLimitEnabled  = $featureToggleRepository->enabled($dailyLimitId);
            $dailyLimitvalue    = $featureToggleRepository->valueOf($dailyLimitId);

            $monthlyLimitId       = FeatureToggle::USER_CHANNEL_ACCOUNT_MONTHLY_LIMIT;
            $monthlyLimitEnabled  = $featureToggleRepository->enabled($monthlyLimitId);
            $monthlyLimitvalue    = $featureToggleRepository->valueOf($monthlyLimitId);

            $onlineUsers->transform(function($value) use ($dailyLimitvalue, $monthlyLimitvalue) {
                $value->user_channel_account_daily_limit_value = $dailyLimitvalue;
                $value->user_channel_account_monthly_limit_value = $monthlyLimitvalue;
                return $value;
            });

        return OnlineReadyForMatchingUserCollection::make($onlineUsers)->additional([
            'meta' => [
                'daily_limit_enabled' => $dailyLimitEnabled,
                'monthly_limit_enabled' => $monthlyLimitEnabled
            ]
        ]);
    }
}
