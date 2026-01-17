<?php


namespace App\Utils;


use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class UserChannelUtil
{

    public function disable(UserChannel $userChannel)
    {
        DB::transaction(function () use ($userChannel) {
            $userChannel->update([
                'status' => UserChannel::STATUS_DISABLED,
            ]);

            UserChannelAccount::where([
                'user_id' => $userChannel->user_id,
                'status'  => UserChannelAccount::STATUS_ONLINE,
            ])->whereHas('channelAmount.channelGroup', function (Builder $channelGroupBuilder) use ($userChannel) {
                $channelGroupBuilder->where('channel_group_id', $userChannel->channel_group_id);
            })->update([
                'status' => UserChannelAccount::STATUS_ENABLE,
            ]);
        });
    }

    public function enable(UserChannel $userChannel)
    {
        abort_if(
            is_null($userChannel->fee_percent),
            Response::HTTP_BAD_REQUEST,
            __('channel.Please set fee first')
        );

        $userChannel->update(['status' => User::STATUS_ENABLE]);
    }

    public function isInvalidFee(UserChannel $userChannel, $targetFeePercent)
    {
        $user = $userChannel->user;

        $parentFeeComparator = $user->role === User::ROLE_PROVIDER ? '<' : '>';
        $childFeeComparator = $user->role === User::ROLE_PROVIDER ? '>' : '<';

        $query = User::where('id', $user->getKey())
            ->where(function ($users) use (
                $userChannel,
                $targetFeePercent,
                $parentFeeComparator,
                $childFeeComparator
            ) {
                $users
                    ->whereHas('parent.userChannels',
                        function (Builder $parentUserChannels) use (
                            $userChannel,
                            $parentFeeComparator,
                            $targetFeePercent
                        ) {
                            $parentUserChannels->where([
                                ['channel_group_id', $userChannel->channel_group_id],
                                ['fee_percent', $parentFeeComparator, $targetFeePercent],
                                ['fee_percent', '!=', 0],
                            ]);
                        })
                    ->orWhereHas('descendants.userChannels',
                        function (Builder $childUserChannels) use (
                            $userChannel,
                            $childFeeComparator,
                            $targetFeePercent
                        ) {
                            $childUserChannels->where([
                                ['channel_group_id', $userChannel->channel_group_id],
                                ['fee_percent', $childFeeComparator, $targetFeePercent],
                                ['fee_percent', '!=', 0],
                            ]);
                        });
            });

        return $query->exists();
    }

    public function updateFee(UserChannel $userChannel, $feePercent)
    {
        DB::transaction(function () use ($userChannel, $feePercent) {
            if ($feePercent == 0) {
                UserChannel::whereHas('user', function ($builder) use ($userChannel) {
                    $builder->whereDescendantOrSelf($userChannel->user);
                })
                    ->where([
                        ['channel_group_id', $userChannel->channel_group_id],
                    ])->update([
                        'fee_percent' => 0,
                    ]);
            } else {
                $userChannel->update(['fee_percent' => $feePercent]);
            }

            UserChannelAccount::where([
                'user_id' => $userChannel->user_id,
            ])->whereHas('channelAmount.channelGroup', function (Builder $channelGroupBuilder) use ($userChannel) {
                $channelGroupBuilder->where('channel_group_id', $userChannel->channel_group_id);
            })->update([
                'fee_percent' => $feePercent,
            ]);
        });
    }

    public function updateMaxAmount(UserChannel $userChannel, $maxAmount)
    {
        DB::transaction(function () use ($userChannel, $maxAmount) {
            $userChannel->update(['max_amount' => $maxAmount]);

            UserChannelAccount::where([
                'user_id' => $userChannel->user_id,
            ])->whereHas('channelAmount.channelGroup', function (Builder $channelGroupBuilder) use ($userChannel) {
                $channelGroupBuilder->where('channel_group_id', $userChannel->channel_group_id);
            })->update([
                'max_amount' => $maxAmount,
            ]);
        });
    }

    public function updateMinAmount(UserChannel $userChannel, $minAmount)
    {
        DB::transaction(function () use ($userChannel, $minAmount) {
            $userChannel->update(['min_amount' => $minAmount]);

            UserChannelAccount::where([
                'user_id' => $userChannel->user_id,
            ])->whereHas('channelAmount.channelGroup', function (Builder $channelGroupBuilder) use ($userChannel) {
                $channelGroupBuilder->where('channel_group_id', $userChannel->channel_group_id);
            })->update([
                'min_amount' => $minAmount,
            ]);
        });
    }
}
