<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Transaction;
use App\Models\FeatureToggle;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Repository\FeatureToggleRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UserChannelAccountStatController extends Controller
{

    public function index(FeatureToggleRepository $featureToggleRepository)
    {
        $lateNightBankLimitEnabled = $featureToggleRepository->enabled(FeatureToggle::LATE_NIGHT_BANK_LIMIT);
        $cancelPaufenMechanismEnabled = $featureToggleRepository->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM);

        $userChannelAccountStats = DB::table('user_channel_accounts')
            ->leftJoin('users', 'users.id', '=', 'user_channel_accounts.user_id')
            ->leftJoin('channel_amounts', 'channel_amounts.id', '=', 'user_channel_accounts.channel_amount_id')
            ->leftJoin('channels', 'channels.code', '=', 'channel_amounts.channel_code')
            ->leftJoin('device_paying_transactions', 'device_paying_transactions.user_channel_account_id', '=',
                'user_channel_accounts.id')
            ->when(!$cancelPaufenMechanismEnabled, function ($query) {
                $query->where('users.status', User::STATUS_ENABLE)
                ->where('users.transaction_enable', true)
                ->where('users.ready_for_matching', true);
            })
            ->when($lateNightBankLimitEnabled, function ($query) {
                $query->where('user_channel_accounts.time_limit_disabled', false);
            })
            ->whereNull('user_channel_accounts.deleted_at')
            ->where('user_channel_accounts.status', UserChannelAccount::STATUS_ONLINE)
            ->whereNotNull('user_channel_accounts.fee_percent')
            ->groupBy(['channels.name', DB::raw('device_paying_transactions.transaction_id IS NOT NULL')])
            ->select([
                'channels.name AS channel_name',
                DB::raw('device_paying_transactions.transaction_id IS NOT NULL AS paying'),
                DB::raw('count(user_channel_accounts.id) AS total'),
            ])
            ->get()
            ->keyBy(function ($userChannelAccountStat) {
                return "{$userChannelAccountStat->channel_name}_{$userChannelAccountStat->paying}";
            });

        $totalUserChannelAccountCount = UserChannelAccount::where('status', UserChannelAccount::STATUS_ONLINE)
            ->whereIn('type', [UserChannelAccount::TYPE_DEPOSIT_WITHDRAW, UserChannelAccount::TYPE_DEPOSIT, UserChannelAccount::TYPE_WITHDRAW])
            ->when($lateNightBankLimitEnabled, function ($query) {
                $query->where('time_limit_disabled', false);
            })
            ->when(!$cancelPaufenMechanismEnabled, function ($query) {
                $query->whereHas('user', function (Builder $user) {
                    $user->where([
                        ['ready_for_matching', true],
                        ['transaction_enable', true],
                        ['role', User::ROLE_PROVIDER],
                    ]);
                });
            })
            ->count();

        $channels = Channel::all();

        $withdrawOrder = Transaction::whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_WITHDRAW])
            ->whereIn('status', [Transaction::STATUS_PAYING, Transaction::STATUS_MATCHING])->count();

        return response()->json([
            'data' => [
                'total'          => $totalUserChannelAccountCount,
                'withdraw_orders' => $withdrawOrder,
                'channels'       => $channels->map(function (Channel $channel) use ($userChannelAccountStats) {
                    return [
                        'channel_name' => $channel->name,
                        'paying'       => data_get($userChannelAccountStats, "{$channel->name}_1.total", 0)
                    ];
                }),
            ]
        ]);
    }
}
