<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserChannelRequest;
use App\Http\Resources\UserChannel;
use App\Models\ChannelAmount;
use App\Models\UserChannel as UserChannelModel;
use App\Utils\BCMathUtil;
use App\Utils\UserChannelUtil;
use Illuminate\Http\Response;
use App\Notifications\ChangeChannelFee;
use Illuminate\Support\Facades\Notification;
use App\Utils\WhitelistedIpManager;

class UserChannelController extends Controller
{

    public function update(
        UserChannelModel $userChannel,
        UpdateUserChannelRequest $request,
        UserChannelUtil $userChannelUtil,
        BCMathUtil $bcMath,
        WhitelistedIpManager $whitelistedIpManager
    ) {
        if ($request->has('fee_percent')) {
            abort_if(
                is_null($request->fee_percent),
                Response::HTTP_BAD_REQUEST,
                __('channel.Fee can not be updated to null')
            );

            if ($userChannel->user->parent) {
                $agentUserChannel = UserChannelModel::where([
                    'channel_group_id' => $userChannel->channel_group_id,
                    'user_id'          => $userChannel->user->parent->getKey(),
                ])->first();

                abort_if(
                    $agentUserChannel
                    && is_null($agentUserChannel->fee_percent),
                    Response::HTTP_BAD_REQUEST,
                    __('channel.Invalid fee')
                );

                // 只有上級為信用模式時，才可以設定下級為信用模式
                // abort_if(
                //     $agentUserChannel
                //     && $bcMath->notEqual($agentUserChannel->fee_percent, 0)
                //     && $bcMath->eq($request->fee_percent, 0),
                //     Response::HTTP_BAD_REQUEST,
                //     __('channel.Zero fee can only be used in root')
                // );

                abort_if(
                    $agentUserChannel
                    && $bcMath->eq($agentUserChannel->fee_percent, 0)
                    && $bcMath->notEqual($request->fee_percent, 0),
                    Response::HTTP_BAD_REQUEST,
                    __('channel.Invalid fee')
                );
            }

            abort_if(
                $bcMath->gtZero($request->fee_percent)
                && $userChannelUtil->isInvalidFee(
                    $userChannel,
                    $request->fee_percent
                ),
                Response::HTTP_BAD_REQUEST,
                __('channel.Invalid fee')
            );

            $before = $userChannel->fee_percent;
            $userChannelUtil->updateFee($userChannel, $request->fee_percent);

            if ($before != $request->fee_percent) {
            Notification::route('telegram', config('services.telegram-bot-api.system-admin-group-id'))
                ->notify(
                    new ChangeChannelFee(auth()->user()->realUser(), $userChannel, $whitelistedIpManager->extractIpFromRequest($request), $before, $request->fee_percent)
                );
            }
        }

        $channelAmount = ChannelAmount::where('channel_group_id', $userChannel->channel_group_id)->select('min_amount','max_amount')->first();

        if ($request->has('min_amount')) {
            abort_if($request->min_amount !== null && $channelAmount->min_amount > $request->min_amount, Response::HTTP_BAD_REQUEST, '不得小于通道金额');
            abort_if($request->min_amount !== null && $channelAmount->max_amount < $request->min_amount, Response::HTTP_BAD_REQUEST, '不得大于通道金额');
            $userChannelUtil->updateMinAmount($userChannel, $request->min_amount);
        }

        if ($request->has('max_amount')) {
            abort_if($request->max_amount !== null && $channelAmount->max_amount < $request->max_amount, Response::HTTP_BAD_REQUEST, '不得大于通道金额');
            abort_if($request->max_amount !== null && $channelAmount->min_amount > $request->max_amount, Response::HTTP_BAD_REQUEST, '不得小于通道金额');
            $userChannelUtil->updateMaxAmount($userChannel, $request->max_amount);
        }

        if ($request->status === UserChannelModel::STATUS_DISABLED) {
            $userChannelUtil->disable($userChannel);
        }

        if ($request->status === UserChannelModel::STATUS_ENABLED) {
            $userChannelUtil->enable($userChannel);
        }

        if ($request->has('real_name_enable')) {
            $userChannel->update(['real_name_enable' => $request->boolean('real_name_enable')]);
        }

        return UserChannel::make($userChannel->refresh());
    }
}
