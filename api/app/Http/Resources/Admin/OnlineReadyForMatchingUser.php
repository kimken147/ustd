<?php

namespace App\Http\Resources\Admin;

use App\Models\Channel;
use App\Models\UserChannelAccount;
use App\Models\ChannelAmount;
use App\Utils\AmountDisplayTransformer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OnlineReadyForMatchingUser extends JsonResource
{

    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request)
    {
        $dailyLimit = $this->daily_limit ?: $this->user_channel_account_daily_limit_value;
        $monthlyLimit = $this->monthly_limit ?: $this->user_channel_account_monthly_limit_value;
        $withdrawDailyLimit = $this->withdraw_daily_limit ?: $this->user_channel_account_daily_limit_value;
        $withdrawMonthlyLimit = $this->withdraw_monthly_limit ?: $this->user_channel_account_monthly_limit_value;

        $channel_code = $this->channelAmount->channel->code;
        $channel_name = $this->channelAmount->channel->name;
        $amount_description = $this->channelAmount->amount_description;

        $bank_name = data_get($this->detail, UserChannelAccount::DETAIL_KEY_BANK_NAME);
        $bank_card_branch = data_get($this->detail, UserChannelAccount::DETAIL_KEY_BANK_CARD_BRANCH);
        $bank_card_holder_name = data_get($this->detail, UserChannelAccount::DETAIL_KEY_BANK_CARD_HOLDER_NAME);
        $account = $this->account;
        $device_name = $this->device->name;

        $accountName = '';
        if (!empty($bank_name)) {
            $accountName = $accountName . $bank_name . '-';
        }

        if (!empty($bank_card_branch)) {
            $accountName = $accountName . $bank_card_branch . '-';
        }

        if (!empty($bank_card_holder_name)) {
            $accountName = $accountName . $bank_card_holder_name . '-';
        }

        if ($this->type == UserChannelAccount::TYPE_WITHDRAW) {
            $accountName = $channel_name . ' 出款专用';
        } else {
            $accountName = $accountName . $account . '(' . $channel_name . ' ' . $amount_description . ')';
        }

        return [
            'id'                       => $this->user->getKey(),
            'name'                     => $this->user->name,
            'username'                 => $this->user->username,
            'available_balance'        => $this->wallet->available_balance,
            'total_paying_count'       => $this->total_paying_count ?? 0,
            'paying_balance'           => $this->total_paying_balance ?? 0,
            'total_withdraw_count'     => $this->total_withdraw_count ?? 0,
            'withdraw_balance'         => $this->total_withdraw_balance ?? 0,
            'hash_id'                  => $this->name,
            'user_channel_accounts_id' => $this->getKey(),
            'user_channel_accounts'    => $accountName,
            'daily_status'             => $this->daily_status,
            'daily_limit'              => $dailyLimit,
            'daily_total'              => $this->daily_total,
            'monthly_status'           => $this->monthly_status,
            'monthly_limit'            => $monthlyLimit,
            'monthly_total'            => $this->monthly_total,
            'withdraw_monthly_limit'   => $withdrawMonthlyLimit,
            'withdraw_monthly_total'   => $this->withdraw_monthly_total,
            'withdraw_daily_limit'     => $withdrawDailyLimit,
            'withdraw_daily_total'     => $this->withdraw_daily_total,
            'device'                   => $this->device,
            'type'                     => $this->type,
            'balance'                  => $this->balance,
            # 單筆限額
            'single_min_limit'                  => $this->single_min_limit,
            'single_max_limit'                  => $this->single_max_limit,
            'withdraw_single_min_limit'         => $this->withdraw_single_min_limit,
            'withdraw_single_max_limit'         => $this->withdraw_single_max_limit,
        ];
    }
}
