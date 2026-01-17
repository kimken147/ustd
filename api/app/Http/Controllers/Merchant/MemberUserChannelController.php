<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserChannelRequest;
use App\Http\Resources\UserChannel;
use App\Models\UserChannel as UserChannelModel;
use App\Utils\BCMathUtil;
use App\Utils\UserChannelUtil;
use Illuminate\Http\Response;
use App\Models\ChannelAmount;

class MemberUserChannelController extends Controller
{

    public function update(
        UserChannelModel $memberUserChannel,
        UpdateUserChannelRequest $request,
        UserChannelUtil $userChannelUtil,
        BCMathUtil $bcMath
    ) {
        if ($request->has('fee_percent')) {
            abort_if(
                is_null($request->fee_percent),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );

            /** @var UserChannelModel $agentUserChannel */
            $agentUserChannel = UserChannelModel::where([
                'channel_group_id' => $memberUserChannel->channel_group_id,
                'user_id'          => $memberUserChannel->user->parent->getKey(),
            ])->firstOrFail();

            abort_if(
                is_null($agentUserChannel->fee_percent),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );

            // 只有上級為信用模式時，才可以設定下級為信用模式
            abort_if(
                $bcMath->notEqual($agentUserChannel->fee_percent, 0)
                && $bcMath->eq($request->fee_percent, 0),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );

            abort_if(
                $bcMath->eq($agentUserChannel->fee_percent, 0)
                && $bcMath->notEqual($request->fee_percent, 0),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );

            abort_if(
                $userChannelUtil->isInvalidFee(
                    $memberUserChannel,
                    $request->fee_percent
                ),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );

            $userChannelUtil->updateFee($memberUserChannel, $request->fee_percent);
        }

        if ($request->status === UserChannelModel::STATUS_DISABLED) {
            $userChannelUtil->disable($memberUserChannel);
        }

        if ($request->status === UserChannelModel::STATUS_ENABLED) {
            $userChannelUtil->enable($memberUserChannel);
        }

        $channelAmount = ChannelAmount::where('channel_group_id', $memberUserChannel->channel_group_id)->select('min_amount','max_amount')->first();
        
        if ($request->has('min_amount')) {
            abort_if($request->min_amount !== null && $channelAmount->min_amount > $request->min_amount, Response::HTTP_BAD_REQUEST, '不得小于通道金额');
            abort_if($request->min_amount !== null && $channelAmount->max_amount < $request->min_amount, Response::HTTP_BAD_REQUEST, '不得大于通道金额');
            $userChannelUtil->updateMinAmount($memberUserChannel, $request->min_amount);
        }

        if ($request->has('max_amount')) {
            abort_if($request->max_amount !== null && $channelAmount->max_amount < $request->max_amount, Response::HTTP_BAD_REQUEST, '不得大于通道金额');
            abort_if($request->max_amount !== null && $channelAmount->min_amount > $request->max_amount, Response::HTTP_BAD_REQUEST, '不得小于通道金额');
            $userChannelUtil->updateMaxAmount($memberUserChannel, $request->max_amount);
        }

        return UserChannel::make($memberUserChannel->refresh());
    }
}
