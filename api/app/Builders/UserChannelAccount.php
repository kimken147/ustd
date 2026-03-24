<?php

namespace App\Builders;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;
use Hashids\Hashids;
use App\Models\UserChannelAccount as UserChannelAccountModel;
use App\Models\User;
use App\Models\FeatureToggle;
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

        $userChannelAccounts->when(!empty($request->bank), function ($builder) use ($request) {
            // bank[] 可能是傳統 bank_id 或 USDT 鏈網路（bank_id=0，存在 detail->chain_network）
            $bankNames = \App\Models\Bank::whereIn('id', $request->bank)->pluck('name');
            $chainCodes = $bankNames->map(fn($name) => strtolower(str_replace('-', '', $name)))->filter()->values()->toArray();

            $builder->where(function ($q) use ($request, $chainCodes) {
                $q->whereIn('bank_id', $request->bank);
                if (!empty($chainCodes)) {
                    $q->orWhereIn('detail->' . UserChannelAccountModel::DETAIL_KEY_CHAIN_NETWORK, $chainCodes);
                }
            });
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

        $userChannelAccounts->when($request->filled('address_type'), function ($builder) use ($request) {
            $builder->where('address_type', $request->address_type);
        });

        $userChannelAccounts->when($request->filled('receive_status'), function ($builder) use ($request) {
            $builder->where('receive_status', $request->receive_status);
        });

        $userChannelAccounts->when($request->filled('parent_account'), function ($builder) use ($request) {
            $parentIds = UserChannelAccountModel::where('address_type', UserChannelAccountModel::ADDRESS_TYPE_MASTER)
                ->where('account', 'like', "%{$request->parent_account}%")
                ->pluck('id');

            $builder->where(function ($q) use ($parentIds) {
                $q->whereIn('parent_account_id', $parentIds)
                  ->orWhereIn('id', $parentIds);
            });
        });

        $userChannelAccounts->orderByDesc('id');

        return $userChannelAccounts;
    }
}
