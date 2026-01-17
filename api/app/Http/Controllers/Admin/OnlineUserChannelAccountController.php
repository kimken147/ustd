<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\OnlineUserChannelAccountCollection;
use App\Model\User;
use App\Model\UserChannelAccount;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OnlineUserChannelAccountController extends Controller
{
    public function index(Request $request)
    {
        $this->validate($request, [
            'min_amount' => 'nullable|numeric',
            'max_amount' => 'nullable|numeric',
            'channel_code' => 'array',
            'channel_code.*' => 'string',
        ]);

        /** @var LengthAwarePaginator $onlineUserChannelAccounts */
        $onlineUserChannelAccounts = DB::table('user_channel_accounts')
            ->leftJoin('users', 'users.id', '=', 'user_channel_accounts.user_id')
            ->leftJoin('channel_amounts', 'channel_amounts.id', '=', 'user_channel_accounts.channel_amount_id')
            ->leftJoin('channels', 'channels.code', '=', 'channel_amounts.channel_code')
            ->leftJoin('device_paying_transactions', 'device_paying_transactions.user_channel_account_id', '=', 'user_channel_accounts.id')
            ->leftJoin('devices', 'devices.id', '=', 'user_channel_accounts.device_id')
            ->when($request->min_amount, function ($query, $minAmount) {
                $query->where(function ($query) use ($minAmount) {
                    $query->where('user_channel_accounts.min_amount', '>=', $minAmount)
                        ->orWhere('channel_amounts.min_amount', '>=', $minAmount);
                });
            })
            ->when($request->max_amount, function ($query, $maxAmount) {
                $query->where(function ($query) use ($maxAmount) {
                    $query->where('user_channel_accounts.max_amount', '<=', $maxAmount)
                        ->orWhere('channel_amounts.max_amount', '<=', $maxAmount);
                });
            })
            ->when(!empty($request->channel_code), function ($query) use ($request) {
                $query->whereIn('channel_amounts.channel_code', $request->channel_code);
            })
            ->where('users.ready_for_matching', true)
            ->where('user_channel_accounts.time_limit_disabled', false)
            ->whereNull('user_channel_accounts.deleted_at')
            ->where('user_channel_accounts.status', UserChannelAccount::STATUS_ONLINE)
            ->whereNotNull('user_channel_accounts.fee_percent')
            ->oldest('user_channel_accounts.last_matched_at')
            ->select([
                'user_channel_accounts.user_id',
                'channels.name AS channel_name',
                'channel_amounts.min_amount AS ca_min_amount',
                'channel_amounts.max_amount AS ca_max_amount',
                'user_channel_accounts.min_amount AS uca_min_amount',
                'user_channel_accounts.max_amount AS uca_max_amount',
                'devices.name AS device_name',
                'user_channel_accounts.last_matched_at AS last_matched_at',
                DB::raw('device_paying_transactions.transaction_id IS NOT NULL AS paying'),
            ])
            ->paginate();

        $users = User::whereIn('id', $onlineUserChannelAccounts->pluck('user_id'))->with('parent')->get()->keyBy('id');

        $onlineUserChannelAccounts = $onlineUserChannelAccounts->map(function ($onlineUserChannelAccount) use ($users) {
            $onlineUserChannelAccount->user = $users->get($onlineUserChannelAccount->user_id);

            return $onlineUserChannelAccount;
        });

        return OnlineUserChannelAccountCollection::make($onlineUserChannelAccounts);
    }
}
