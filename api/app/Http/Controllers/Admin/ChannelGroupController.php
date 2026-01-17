<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ChannelGroupCollection;
use App\Models\Channel;
use App\Models\ChannelAmount;
use App\Models\ChannelGroup;
use App\Models\User;
use App\Models\UserChannel;
use App\Models\UserChannelAccount;
use App\Utils\BCMathUtil;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Repository\FeatureToggleRepository;

class ChannelGroupController extends Controller
{

    public function destroy(ChannelGroup $channelGroup)
    {
        DB::transaction(function () use ($channelGroup) {
            $userChannelAccounts = UserChannelAccount::whereHas('channelAmount', function (Builder $channelAmountBuilder) use ($channelGroup) {
                $channelAmountBuilder->where('channel_group_id', $channelGroup->getKey());
            })
                ->exists();

            abort_if($userChannelAccounts, Response::HTTP_FORBIDDEN, '请先删除此金额的收付款号，再次尝试');

            $channelGroup->channelAmounts()->delete();

            $channelGroup->userChannels()->delete();

            $channelGroup->delete();
        });

        return response()->noContent();
    }

    public function index(Request $request)
    {
        $this->validate($request, [
            'no_paginate' => 'bool',
            "is_provider" => "nullable|bool"
        ]);


        $query = ChannelGroup::with("channel")->whereHas("channel", function ($builder) use ($request) {
            $builder->when($request->is_provider, function ($x) {
                $x->where("third_exclusive_enable", false);
            });
        });

        return ChannelGroupCollection::make(
            $request->boolean('no_paginate') ? $query->get() : $query->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'channel_code' => 'required|string',
            'amount'       => 'required|string',
        ]);

        $channel = Channel::findOrFail($request->channel_code);

        [$channelAmounts, $isFixed] = $this->parseAmount($request->amount);

        abort_if($this->isDuplicated($channel->channelAmounts, $channelAmounts[0], $isFixed), Response::HTTP_BAD_REQUEST, __('channel-group.Amount Duplicate'));

        $channelGroup = DB::transaction(function () use ($channel, $channelAmounts, $isFixed) {
            $channelGroup = $channel->channelGroups()->create(['fixed_amount' => $isFixed]);

            $this->addNewChannelAmounts($channelGroup, $channelAmounts);

            return $channelGroup;
        });

        return \App\Http\Resources\Admin\ChannelGroup::make($channelGroup);
    }

    private function parseAmount(string $amount)
    {
        $amount = trim($amount, ',~');

        abort_if(
            !Str::contains($amount, [','])
                && !Str::contains($amount, ['~'])
                && !is_numeric($amount),
            Response::HTTP_BAD_REQUEST,
            __('channel-group.Invalid format')
        );

        abort_if(Str::containsAll($amount, [',', '~']), Response::HTTP_BAD_REQUEST, __('channel-group.Invalid format (Can\'t use both)'));

        if (Str::contains($amount, [',']) || is_numeric($amount)) {
            $channelAmounts = collect([['fixed_amount' => str_replace(' ', '', $amount)]]);

            $fixedAmount = true;
        } else {
            [$minAmount, $maxAmount] = explode('~', $amount);

            $minAmount = trim($minAmount);
            $maxAmount = trim($maxAmount);

            abort_if(!is_numeric($minAmount) || !is_numeric($maxAmount) || $minAmount > $maxAmount, Response::HTTP_BAD_REQUEST, __('channel-group.Invalid format'));

            $channelAmounts = collect([
                ['min_amount' => ($minAmount <= 1) ? 1 : $minAmount, 'max_amount' => $maxAmount]
            ]);

            $fixedAmount = false;
        }

        return [$channelAmounts, $fixedAmount];
    }

    private function addNewChannelAmounts(ChannelGroup $channelGroup, Collection $channelAmounts)
    {
        $now = now();

        $channelAmounts = $channelAmounts->map(function ($channelAmount) use ($now, $channelGroup) {
            return [
                'channel_group_id' => $channelGroup->getKey(),
                'channel_code'     => $channelGroup->channel_code,
                'min_amount'       => $channelAmount['min_amount'] ?? null,
                'max_amount'       => $channelAmount['max_amount'] ?? null,
                'fixed_amount'     => isset($channelAmount['fixed_amount']) ?
                    json_encode(explode(',', $channelAmount['fixed_amount'])) :  // 一定要顯示使用 json_encode?
                    null,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];
        });

        $users = User::whereIn('role', [User::ROLE_PROVIDER, User::ROLE_MERCHANT])->get();

        $featureToggleRepository = app(FeatureToggleRepository::class);
        $cancelPaufen = $featureToggleRepository->enabled(\App\Model\FeatureToggle::CANCEL_PAUFEN_MECHANISM);
        $userChannels = $users->map(function ($user) use ($now, $channelGroup, $cancelPaufen) {
            $channel = [
                'user_id'          => $user->getKey(),
                'channel_group_id' => $channelGroup->getKey(),
                'fee_percent'      => 0,
                'floating_enable'  => false,
                'created_at'       => $now,
                'updated_at'       => $now,
            ];

            if ($user->role == User::ROLE_PROVIDER) {
                $channel['status'] = $cancelPaufen ? UserChannel::STATUS_ENABLED : UserChannel::STATUS_DISABLED;
            } else {
                $channel['status'] = UserChannel::STATUS_DISABLED;
            }
            return $channel;
        });

        ChannelAmount::insertIgnore($channelAmounts->toArray());
        UserChannel::insertIgnore($userChannels->toArray());
    }

    private function isDuplicated($currentChannelAmounts, $newChannelAmounts, $isFixed)
    {
        return $currentChannelAmounts->filter(function ($amount) use ($newChannelAmounts, $isFixed) {
            if ($isFixed && $amount->fixed_amount) {
                $intersect = array_intersect($amount->fixed_amount, explode(',', $newChannelAmounts['fixed_amount']));
                return count($intersect) > 0;
            }

            if (!$isFixed && $amount->min_amount == $newChannelAmounts['min_amount'] && $amount->max_amount == $newChannelAmounts['max_amount']) {
                return true;
            }

            return false;
        })->isNotEmpty();
    }

    public function update(Request $request, ChannelGroup $channelGroup, BCMathUtil $bcMath)
    {
        $this->validate($request, [
            'amount' => 'required',
        ]);

        [$channelAmounts, $fixedAmount] = $this->parseAmount($request->amount);

        $currentAmounts = $channelGroup->channel->channelAmounts->where('channel_group_id', '!=', $channelGroup->id);

        abort_if($this->isDuplicated($currentAmounts, $channelAmounts[0], $fixedAmount), Response::HTTP_BAD_REQUEST, __('channel-group.Amount Duplicate'));

        if ($channelGroup->fixed_amount && !$fixedAmount) { // 固定 -> 任意
            DB::transaction(function () use ($channelAmounts, $fixedAmount, $channelGroup) {
                UserChannelAccount::whereHas(
                    'channelAmount',
                    function (Builder $channelAmountBuilder) use ($channelGroup) {
                        $channelAmountBuilder->where('channel_group_id', $channelGroup->getKey());
                    }
                )->delete();

                $channelGroup->channelAmounts()->delete();

                $channelGroup->update(['fixed_amount' => $fixedAmount]);

                $this->addNewChannelAmounts($channelGroup, $channelAmounts);
            });
        } elseif ($channelGroup->fixed_amount && $fixedAmount) { // 固定 -> 固定
            $channelAmount = $channelAmounts->first();

            $channelGroupAmounts = $channelGroup->channelAmounts()->first();

            $channelGroupAmounts->update(['fixed_amount' => explode(',', $channelAmount['fixed_amount'])]);
        } elseif (!$channelGroup->fixed_amount && $fixedAmount) { // 任意 -> 固定
            DB::transaction(function () use ($channelAmounts, $fixedAmount, $channelGroup) {
                UserChannelAccount::whereHas(
                    'channelAmount',
                    function (Builder $channelAmountBuilder) use ($channelGroup) {
                        $channelAmountBuilder->where('channel_group_id', $channelGroup->getKey());
                    }
                )->delete();

                $channelGroup->channelAmounts()->delete();

                $channelGroup->update(['fixed_amount' => $fixedAmount]);

                $this->addNewChannelAmounts($channelGroup, $channelAmounts);
            });
        } else { // 任意 -> 任意
            $channelAmount = $channelAmounts->first();

            $channelGroupAmounts = $channelGroup->channelAmounts()->first();

            UserChannel::where('channel_group_id', $channelGroup->id)
                ->where('min_amount', '<', $channelAmount['min_amount'])
                ->update(['min_amount' => $channelAmount['min_amount']]);

            UserChannel::where('channel_group_id', $channelGroup->id)
                ->where('max_amount', '>', $channelAmount['max_amount'])
                ->update(['max_amount' => $channelAmount['max_amount']]);

            UserChannelAccount::where('channel_amount_id', $channelGroupAmounts->id)
                ->where('min_amount', '<', $channelAmount['min_amount'])
                ->update(['min_amount' => $channelAmount['min_amount']]);

            UserChannelAccount::where('channel_amount_id', $channelGroupAmounts->id)
                ->where('max_amount', '>', $channelAmount['max_amount'])
                ->update(['max_amount' => $channelAmount['max_amount']]);

            $channelGroupAmounts->update([
                'min_amount' => $channelAmount['min_amount'],
                'max_amount' => $channelAmount['max_amount'],
            ]);
        }

        return \App\Http\Resources\Admin\ChannelGroup::make($channelGroup);
    }
}
