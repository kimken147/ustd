<?php

namespace App\Builders;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Hashids\Hashids;
use App\Model\UserChannelAccount as UserChannelAccountModel;
use App\Model\User;
use App\Model\FeatureToggle;
use App\Repository\FeatureToggleRepository;

class UserChannelAccount
{
    public function query($request)
    {
        $featureToggleRepository = app(FeatureToggleRepository::class);

        $userChannelAccounts = UserChannelAccountModel::whereHas('user', function ($builder) {
            $builder->where('role', User::ROLE_PROVIDER);
        })->with('channelAmount.channel', 'device', 'bank');

        $userChannelAccounts->when($featureToggleRepository->enabled(FeatureToggle::SHOW_DELETED_DATA), function ($builder) {
            $builder->withTrashed();
        });

        $userChannelAccounts->when(!is_null($request->name_or_username), function ($builder) use ($request) {
            $builder->whereHas('user', function ($builder) use ($request) {
                $builder->where('name', 'like', "%{$request->name_or_username}%")
                    ->orWhere('username', $request->name_or_username);
            });
        });

        $userChannelAccounts->when(!empty($request->channel_code), function ($builder) use ($request) {
            if ($request->type == UserChannelAccountModel::TYPE_DEPOSIT) {
                $builder->whereHas('channelAmount', function ($builder) use ($request) {
                    $builder->whereIn('channel_code', $request->channel_code);
                });
            } else {
                $builder->whereIn('channel_code', $request->channel_code);
            }
        });

        $userChannelAccounts->when(!empty($request->status), function ($builder) use ($request) {
            $builder->whereIn('status', $request->status);
        });

        $userChannelAccounts->when(!empty($request->type), function ($builder) use ($request) {
            $builder->whereIn('type', $request->type);
        });

        $userChannelAccounts->when($request->has('is_auto'), function ($builder) use ($request) {
            $builder->where('is_auto', $request->is_auto);
        });

        $userChannelAccounts->when($request->filled('account_name'), function ($builder) use ($request) {
            $builder->where(function ($builder) use ($request) {
                $accountName = '%'.$request->input('account_name').'%';
                $builder->where('detail->'.UserChannelAccountModel::DETAIL_KEY_BANK_CARD_HOLDER_NAME, 'like', $accountName)
                    ->orWhere('detail->'.UserChannelAccountModel::DETAIL_KEY_RECEIVER_NAME, 'like', $accountName);
            });
        });

        $userChannelAccounts->when(!empty($request->device_name), function ($builder) use ($request) {
            $builder->whereHas('device', function ($devices) use ($request) {
                $devices->where('name', 'like', "%{$request->device_name}%");
            });
        });

        $userChannelAccounts->when(!empty($request->name), function ($builder) use ($request) {
            $builder->whereIn('name', $request->name);
        });

        $userChannelAccounts->when(!empty($request->note), function ($builder) use ($request) {
            $builder->where('note', 'like', "%{$request->note}%");
        });

        $userChannelAccounts->when(!empty($request->hash_id), function ($builder) use ($request) {
            $id = [];
            foreach($request->hash_id as $hashId) {
                array_push($id, Arr::first((new Hashids())->decode($hashId)));
            }
            $builder->whereIn('id', $id);
        });

        $userChannelAccounts->when(!empty($request->bank_id), function ($builder) use ($request) {
            $builder->whereIn('bank_id', $request->bank_id);
        });

        $userChannelAccounts->when(!is_null($request->bank_card_branch), function ($builder) use ($request) {
            $builder->where('detail->'.UserChannelAccountModel::DETAIL_KEY_BANK_CARD_BRANCH, 'like', "%{$request->bank_card_branch}%");
        });

        $userChannelAccounts->when(!empty($request->account), function ($builder) use ($request) {
            $builder->where('account', 'like', "%{$request->account}%");
        });

        $userChannelAccounts->when(!empty($request->provider_id), function ($builder) use ($request) {
            $builder->where('user_id', $request->provider_id);
        });

        $userChannelAccounts->when(!empty($request->channel_group), function ($builder) use ($request) {
            $builder->whereHas('channelAmount', function ($builder) use ($request) {
                $builder->where('channel_group_id', $request->channel_group);
            });
        });

        $userChannelAccounts->when(!empty($request->auto_sync), function ($builder) use ($request) {
            $builder->whereIn('auto_sync', $request->auto_sync);
        });

        $userChannelAccounts->orderByDesc('id');

        return $userChannelAccounts;
    }
}
