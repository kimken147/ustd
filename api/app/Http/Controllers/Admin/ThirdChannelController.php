<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ThirdChannelCollection;
use App\Models\FeatureToggle;
use App\Models\MerchantThirdChannel;
use App\Models\Permission;
use App\Models\ThirdChannel;
use App\Repository\FeatureToggleRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ThirdChannelController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:' . Permission::ADMIN_UPDATE_USER_CHANNEL_ACCOUNT])->only('update');
        $this->middleware(['permission:' . Permission::ADMIN_DESTROY_USER_CHANNEL_ACCOUNT])->only('destroy');
    }

    public function index(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        $this->validate($request, [
            'name_or_username'       => 'nullable|string',
            'agent_name_or_username' => 'nullable|string',
            'channel_code'           => 'array',
            'status'                 => 'array',
            'device_name'            => 'nullable|string',
            'account_name'           => 'nullable|string',
            'hash_id'                => 'nullable|string',
        ]);

        /** @var Builder $userChannelAccounts */
        $thirdChannel = ThirdChannel::with('channel')->orderBy('id', "desc")
            ->latest();

        $thirdChannel->withCount('merchants');

        $thirdChannel->when(!is_null($request->name_or_username), function ($builder) use ($request) {
            $builder->where('name', 'like', "%{$request->name_or_username}%")
                ->orWhere('class', $request->name_or_username);
        });

        $thirdChannel->when(!empty($request->channel_code), function ($builder) use ($request) {
            $builder->whereIn('channel_code', $request->channel_code);
        });

        $thirdChannel->when(!empty($request->status), function ($builder) use ($request) {
            $builder->whereIn('status', $request->status);
        });

        $data = $request->no_paginate ? $thirdChannel->get() : $thirdChannel->paginate(20);

        return ThirdChannelCollection::make($data);
    }

    public function update(Request $request)
    {
        abort_if(!$request->id && !$request->type, Response::HTTP_NOT_FOUND);

        if ($request->is_batch) {
            // 批量更新該三方費率
            $thirdchannel = ThirdChannel::find($request->id);
            $update = $request->only(
                'deposit_fee_percent',
                'withdraw_fee',
                'daifu_fee_percent',
                'deposit_min',
                'deposit_max',
                'daifu_min',
                'daifu_max'
            );

            if (count($update) > 0) {
                MerchantThirdChannel::where('thirdchannel_id', $request->id)->update($update);
            }
        } elseif (!$request->type) {
            $thirdchannel = ThirdChannel::where('id', $request->id)->first();

            $this->validate($request, [
                'status' => [Rule::in(ThirdChannel::STATUS_ENABLE, ThirdChannel::STATUS_DISABLE), 'nullable'],
                'merchant_id' => ['string', 'nullable'],
                'key'  => ['string', 'nullable'],
                'key2' => ['string', 'nullable'],
                'key3' => ['string', 'nullable'],
                'type2' => [Rule::in(ThirdChannel::TYPE_DEPOSIT_WITHDRAW, ThirdChannel::TYPE_DEPOSIT_ONLY, ThirdChannel::TYPE_WITHDRAW_ONLY), 'nullable'],
            ]);

            $updateData = $request->only(['status', 'custom_url', 'white_ip', 'notify_balance', 'auto_daifu_threshold', 'auto_daifu_threshold_min', 'merchant_id', 'key', 'key2', 'key3']);

            if ($request->has("type2")) {
                $updateData["type"] = $request->type2;
            }

            $thirdchannel->update($updateData);
        } else {
            $thirdchannels = ThirdChannel::all();

            if ($request->type === 'threshold') {
                foreach ($thirdchannels as $thirdchannel) {
                    $thirdchannel->update(['auto_daifu_threshold' => $request->auto_daifu_threshold]);
                }
            } else {
                foreach ($thirdchannels as $thirdchannel) {
                    $thirdchannel->update(['notify_balance' => $request->notify_balance ?? 0]);
                }
            }
        }
        return \App\Http\Resources\ThirdChannel::make($thirdchannel);
    }
}
