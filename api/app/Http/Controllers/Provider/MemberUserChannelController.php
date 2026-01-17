<?php

namespace App\Http\Controllers\Provider;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserChannelRequest;
use App\Http\Resources\UserChannel;
use App\Models\UserChannel as UserChannelModel;
use App\Utils\BCMathUtil;
use App\Utils\UserChannelUtil;
use Illuminate\Http\Response;

class MemberUserChannelController extends Controller
{

    public function update(
        UserChannelModel $memberUserChannel,
        UpdateUserChannelRequest $request,
        UserChannelUtil $userChannelUtil,
        BCMathUtil $bcMath
    ) {
        abort(
            Response::HTTP_BAD_REQUEST,
            '请联络客服修改'
        );

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

        return UserChannel::make($memberUserChannel->refresh());
    }
}
