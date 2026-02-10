<?php

namespace App\Http\Controllers\Provider;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

use App\Utils\AmountDisplayTransformer;
use App\Utils\DateRangeValidator;
use App\Http\Controllers\Controller;

use App\Http\Resources\UserChannelAccountCollection;
use App\Http\Resources\UserCollection;
use App\Http\Resources\WalletHistoryCollection;

use App\Http\Resources\Provider\TransactionCollection;
use App\Http\Resources\Provider\DepositCollection;
use App\Http\Resources\Provider\WithdrawCollection;
use App\Http\Resources\Provider\BankCardCollection;
use App\Http\Resources\Provider\DeviceCollection;

use App\Repository\FeatureToggleRepository;
use App\Models\FeatureToggle;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserChannelAccount;
use App\Models\WalletHistory;
use App\Models\BankCard;
use App\Models\Device;

class GenericSearchController extends Controller
{
    public function __invoke(Request $request, FeatureToggleRepository $featureToggleRepository)
    {
        $this->validate($request, [
            'type' => 'required',
            'started_at' => ['date_format:'.DateTimeInterface::ATOM],
            'ended_at' => ['date_format:'.DateTimeInterface::ATOM],
        ]);

        $type = $request->type;
        $dateRange = DateRangeValidator::parse($request);
        $startedAt = $dateRange->startedAt;
        $endedAt = $dateRange->endedAt;
        $userId = auth()->user()->getKey();

        if ($type == 'transaction') {
            $dateRange->validateDaysFromFeatureToggle($featureToggleRepository);

            $query = Transaction::where('type', Transaction::TYPE_PAUFEN_TRANSACTION)
                ->where('from_id', $userId)
                ->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')))
                ->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')))
                ->where(function ($builder) use ($request) {
                    $builder
                        ->where('amount', $request->q)
                        ->orWhere('system_order_number', 'like', "%{$request->q}%")
                        ->orWhere('from_channel_account->account', 'like', "%{$request->q}%")
                        ->orWhere('to_channel_account->real_name', 'like', "%{$request->q}%")
                        ->orWhere('from_device_name', 'like', "%{$request->q}%")
                        ->orWhere('note', 'like', "%{$request->q}%");
                });

            $stats = (clone $query)->first([
                DB::raw('SUM(amount) AS total_amount'),
            ]);

            return TransactionCollection::make($request->no_paginate ? $query->get() : $query->paginate())
                ->additional([
                    'meta' => [
                        'total_amount' => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
                    ]
                ]);
        } else if ($type == 'wallet-history') {
            $dateRange->validateDaysFromFeatureToggle($featureToggleRepository);

            $query = WalletHistory::where('user_id', $userId)
                ->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')))
                ->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')))
                ->where(function ($builder) use ($request) {
                    $builder
                        ->where('note', 'like', "%{$request->q}%")
                        ->orWhere('result->balance', 'like', "%{$request->q}%")
                        ->orWhere('delta->balance', 'like', "%{$request->q}%");
                });

            $walletBalanceTotal = (clone $query)
                ->first([
                    DB::raw('SUM(delta->>"$.balance") + SUM(delta->>"$.profit") + SUM(delta->>"$.frozen_balance")AS total')
                ]);

            return WalletHistoryCollection::make($query->latest('created_at')->latest('id')->paginate()->appends($request->query->all()))
                ->additional([
                    'meta' => [
                        'wallet_balance_total' => AmountDisplayTransformer::transform(data_get($walletBalanceTotal, 'total', '0.00'))
                    ]
                ]);
        } else if ($type == 'channel-account') {
            $query = UserChannelAccount::where('user_id', $userId)
                ->where(function ($builder) use ($request) {
                    $builder
                        ->where('account', 'like', "%{$request->q}%")
                        ->orWhere('detail->bank_name', 'like', "%{$request->q}%")
                        ->orWhere('detail->bank_card_number', 'like', "%{$request->q}%")
                        ->orWhere('detail->bank_card_holder_name', 'like', "%{$request->q}%")
                        ->orWhere('detail->bank_card_branch', 'like', "%{$request->q}%")
                        ->orWhereHas('device', function ($devices) use ($request) {
                            $devices->where('name', 'like', "%{$request->q}%");
                        });
                });

            return UserChannelAccountCollection::make($request->no_paginate ? $query->get() : $query->paginate());

        } else if ($type == 'device') {
            $query = Device::with('userChannelAccounts')
                ->where('user_id', $userId)
                ->where(function ($builder) use ($request) {
                    $builder
                        ->where('name', 'like', "%{$request->q}%")
                        ->orWhereHas('userChannelAccounts', function ($accounts) use ($request) {
                            $accounts->where('account', 'like', "%{$request->q}%")
                                ->orWhere('detail->bank_name', 'like', "%{$request->q}%")
                                ->orWhere('detail->bank_card_holder_name', 'like', "%{$request->q}%");
                        });
                });

            return DeviceCollection::make($request->no_paginate ? $query->get() : $query->paginate());

        } else if ($type == 'deposit') {
            $dateRange->validateDaysFromFeatureToggle($featureToggleRepository);

            $query = Transaction::whereIn('type', [Transaction::TYPE_PAUFEN_WITHDRAW, Transaction::TYPE_NORMAL_DEPOSIT])
            ->where('to_id', $userId)
            ->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')))
            ->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')))
            ->where(function ($builder) use ($request) {
                $builder
                    ->where('amount', $request->q)
                    ->orWhere('system_order_number', 'like', "%{$request->q}%");
            });

            $stats = (clone $query)->first([
                DB::raw('SUM(amount) AS total_amount'),
            ]);

            return DepositCollection::make($request->no_paginate ? $query->get() : $query->paginate())
                ->additional([
                    'meta' => [
                        'total_amount' => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
                    ]
                ]);

        } else if ($type == 'bank-card') {
            $query = BankCard::where('user_id', $userId)->where(function ($builder) use ($request) {
                $builder
                    ->where('bank_card_holder_name', 'like', "%{$request->q}%")
                    ->orWhere('bank_card_number', 'like', "%{$request->q}%")
                    ->orWhere('bank_name', 'like', "%{$request->q}%");
            });

            return BankCardCollection::make($request->no_paginate ? $query->get() : $query->paginate());
        } else if ($type == 'withdraw') {
            $dateRange->validateDaysFromFeatureToggle($featureToggleRepository);

            $query = Transaction::where('type', Transaction::TYPE_NORMAL_WITHDRAW)
                ->where('from_id', $userId)
                ->where('created_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')))
                ->where('created_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')))
                ->where(function ($builder) use ($request) {
                    $builder
                        ->where('amount', $request->q)
                        ->orWhere('system_order_number', 'like', "%{$request->q}%")
                        ->orWhere('from_channel_account->bank_card_holder_name', 'like', "%{$request->q}%")
                        ->orWhere('from_channel_account->bank_card_number', 'like', "%{$request->q}%")
                        ->orWhere('from_channel_account->bank_name', 'like', "%{$request->q}%");
                });

            $stats = (clone $query)->first([
                DB::raw('SUM(amount) AS total_amount'),
            ]);

            return WithdrawCollection::make($request->no_paginate ? $query->get() : $query->paginate())
                ->additional([
                    'meta' => [
                        'total_amount' => AmountDisplayTransformer::transform($stats->total_amount ?? '0.00'),
                    ]
                ]);
        } else if ($type == 'member') {
            $query = User::where('role', User::ROLE_PROVIDER)
                ->where('parent_id', $userId)
                ->where(function ($builder) use ($request) {
                    $builder->where('name', 'like', "%{$request->q}%")
                        ->orWhere('username', 'like', "%{$request->q}%");
                });

            return UserCollection::make($request->no_paginate ? $query->get() : $query->paginate());
        }

        return response()->noContent();
    }

    public function possibleType($keyword) {
        $types = [];

        if (Str::contains('系统调整', $keyword)) {
            $types[] = 1;
        }
        if (Str::contains('余额转赠', $keyword)) {
            $types[] = 2;
        }
        if (Str::contains('入账', $keyword)) {
            $types[] = 3;
        }
        if (Str::contains('预扣', $keyword)) {
            $types[] = 4;
        }
        if (Str::contains('预扣退款', $keyword)) {
            $types[] = 5;
        }
        if (Str::contains('快充奖励(紅利)', $keyword)) {
            $types[] = 6;
        }
        if (Str::contains('交易奖励(紅利)', $keyword)) {
            $types[] = 7;
        }
        if (Str::contains('入账(扣冻结)', $keyword)) {
            $types[] = 8;
        }
        if (Str::contains('入账(红利)', $keyword)) {
            $types[] = 9;
        }
        if (Str::contains('系统调整(红利)', $keyword)) {
            $types[] = 10;
        }
        if (Str::contains('系统调整(冻结)', $keyword)) {
            $types[] = 11;
        }
        if (Str::contains('提现', $keyword)) {
            $types[] = 12;
        }
        if (Str::contains('提现退款', $keyword)) {
            $types[] = 12;
        }

        return $types;
    }
}
