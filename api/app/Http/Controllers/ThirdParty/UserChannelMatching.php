<?php


namespace App\Http\Controllers\ThirdParty;


use App\Model\Channel;
use App\Model\ChannelAmount;
use App\Model\User;
use App\Model\UserChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
trait UserChannelMatching
{

    /**
     * @param  User  $user
     * @param  Channel  $channel
     * @param  string  $amount
     * @return array
     */
    private function findSuitableUserChannel(
        User $merchant,
        Channel $channel,
        string $amount
    ) {

        $userChannels = UserChannel::with('channelGroup.channelAmount')->where([
            ['user_id', $merchant->getKey()],
            ['status', UserChannel::STATUS_ENABLED]
        ])->whereHas('channelGroup.channelAmounts', function (Builder $channelGroups) use ($channel) {
            $channelGroups->where('channel_code', $channel->getKey());
        })->get();

        $userChannels = $userChannels->filter(function ($userChannel) use ($amount) {
            $channelAmount = $userChannel->channelGroup->channelAmount;
            $minAmount = $userChannel->min_amount ?? $channelAmount->min_amount;
            $maxAmount = $userChannel->max_amount ?? $channelAmount->max_amount;

            if ($minAmount && $maxAmount) {
                return $amount >= $minAmount && $amount <= $maxAmount;
            }

            if ($channelAmount->fixed_amount) {
                return in_array($amount, $channelAmount->fixed_amount);
            }

            return false;
        });

        if (!$userChannels) {
            return [null, null];
        }

        $userChannel = $userChannels->filter(function ($channel) use ($amount) {
            if ($channel->min_amount && $channel->min_amount > $amount) {
                return false;
            }
            if ($channel->max_amount && $channel->max_amount < $amount) {
                return false;
            }
            return true;
        })->first();

        if (!$userChannel) {
            return [null, null];
        }

        $channelAmounts = ChannelAmount::where([
            ['channel_group_id', $userChannel->channel_group_id],
        ])->get();

        $channelAmount = $channelAmounts->filter(function ($channelAmount) use ($amount) {
            return ($amount >= $channelAmount->min_amount && $amount <= $channelAmount->max_amount) ||
                   ($channelAmount->fixed_amount && in_array($amount, $channelAmount->fixed_amount));
        })->first();

        if (!$channelAmount) {
            return [$userChannel, null];
        }

        return [$userChannel, $channelAmount];
    }

}
