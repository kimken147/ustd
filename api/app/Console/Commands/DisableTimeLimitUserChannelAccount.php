<?php

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\TimeLimitBank;
use App\Models\UserChannelAccount;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DisableTimeLimitUserChannelAccount extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'paufen:disable-time-limit-user-channel-account {user_channel_account}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '關閉收款帳號時間限制';

    /**
     * @var UserChannelAccount|null
     */
    public $userChannelAccount;

    /**
     * Create a new command instance.
     *
     * @param  UserChannelAccount|null  $userChannelAccount
     */
    public function __construct(?UserChannelAccount $userChannelAccount = null)
    {
        parent::__construct();
        $this->userChannelAccount = $userChannelAccount;
    }

    public function handle()
    {
        $now = now()->setSecond(1);
        $lateNightStartedAt = Carbon::make('00:00:00');
        $lateNightEndedAt = Carbon::make('05:00:00');

        $lateNightNow = $now->between($lateNightStartedAt, $lateNightEndedAt);

        $timeLimitBanks = TimeLimitBank::where('bank_id', '>', 0)->get()->map(function (TimeLimitBank $timeLimitBank) use ($now) {
            if ($timeLimitBank->started_at->gte($timeLimitBank->ended_at)) {
                if ($now->gte($timeLimitBank->started_at)) {
                    $timeLimitBank->ended_at = $timeLimitBank->ended_at->addDay();
                } else {
                    $timeLimitBank->started_at = $timeLimitBank->started_at->subDay();
                }
            }
            return $timeLimitBank;
        });

        $enabledBankIds  = $this->computeEnabledBankNames($now, $timeLimitBanks, 'bank_id');
        $disabledBankIds = $this->computeDisabledBankNames($now, $timeLimitBanks, 'bank_id');

        $baseUserChannelAccountQuery = UserChannelAccount::withTrashed()
            ->when($this->userChannelAccount, function (Builder $builder) {
                $builder->where('id', $this->userChannelAccount->getKey());
            })
            ->whereHas('channelAmount', function (Builder $builder) {
                $builder->whereIn('channel_code', [Channel::CODE_ALIPAY_BANK, Channel::CODE_BANK_CARD]);
            });

        DB::transaction(function () use (
            $lateNightNow,
            $disabledBankIds,
            $enabledBankIds,
            $baseUserChannelAccountQuery
        ) {
            if ($lateNightNow) {
                $this->applyWhereNotInBankIdScope(
                    clone $baseUserChannelAccountQuery,
                    $enabledBankIds
                )
                    ->update([
                        'time_limit_disabled' => true,
                    ]);

                if ($enabledBankIds->isNotEmpty()) {
                    $this->applyWhereInBankIdScope(
                        clone $baseUserChannelAccountQuery,
                        $enabledBankIds
                    )
                        ->update([
                            'time_limit_disabled' => false,
                        ]);
                }
            } else {
                $this->applyWhereNotInBankIdScope(
                    clone $baseUserChannelAccountQuery,
                    $disabledBankIds
                )
                    ->update([
                        'time_limit_disabled' => false,
                    ]);

                if ($disabledBankIds->isNotEmpty()) {
                    $this->applyWhereInBankIdScope(
                        clone $baseUserChannelAccountQuery,
                        $disabledBankIds
                    )
                        ->update([
                            'time_limit_disabled' => true,
                        ]);
                }
            }
        });
    }

    private function expandedBankNames(Collection $bankNames)
    {
        $expandedBankNames = collect();
        $transformedBankNames = $bankNames->duplicates();

        foreach ($bankNames as $bankName) {
            $bankName = Str::replaceFirst('中国', '', $bankName);
            $bankName = Str::replaceLast('银行', '', $bankName);

            if (Str::contains($bankName, ['邮政', '邮储', '邮政储蓄'])) {
                $transformedBankNames = $transformedBankNames->merge([
                    '邮政', '邮储',
                ]);
            } elseif (Str::contains($bankName, ['广发', '广东发展'])) {
                $transformedBankNames = $transformedBankNames->merge([
                    '广发',
                ]);
            } else {
                $transformedBankNames->push($bankName);
            }
        }

        foreach ($transformedBankNames as $transformedBankName) {
            if ($transformedBankName === '') {
                $expandedBankNames = $expandedBankNames->merge([
                    '%中国银行%', '中国',
                ]);
            } else {
                $expandedBankNames = $expandedBankNames->merge([
                    "%$transformedBankName%",
                ]);
            }
        }

        return $expandedBankNames;
    }

    private function computeEnabledBankNames(Carbon $now, Collection $timeLimitBanks, $Pluck='bank_name')
    {
        return $timeLimitBanks->filter(function (TimeLimitBank $timeLimitBank) use ($now) {
            if (
                $now->between($timeLimitBank->started_at, $timeLimitBank->ended_at)
                && $timeLimitBank->status === TimeLimitBank::STATUS_ENABLE
            ) {
                return true;
            }

            if (
                !$now->between($timeLimitBank->started_at, $timeLimitBank->ended_at)
                && $timeLimitBank->status === TimeLimitBank::STATUS_DISABLE
            ) {
                return true;
            }

            return false;
        })->pluck($Pluck);
    }

    private function computeDisabledBankNames(Carbon $now, Collection $timeLimitBanks, $Pluck='bank_name')
    {
        return $timeLimitBanks->filter(function (TimeLimitBank $timeLimitBank) use ($now) {
            if (
                $now->between($timeLimitBank->started_at, $timeLimitBank->ended_at)
                && $timeLimitBank->status === TimeLimitBank::STATUS_DISABLE
            ) {
                return true;
            }

            if (
                !$now->between($timeLimitBank->started_at, $timeLimitBank->ended_at)
                && $timeLimitBank->status === TimeLimitBank::STATUS_ENABLE
            ) {
                return true;
            }

            return false;
        })->pluck($Pluck);
    }

    private function applyWhereNotLikeBankNameScope(Builder $baseQuery, Collection $bankNames)
    {
        foreach ($bankNames as $bankName) {
            $baseQuery->where('detail->bank_name', 'not like', $bankName, 'and');
        }

        return $baseQuery;
    }

    private function applyWhereLikeBankNameScope(Builder $baseQuery, Collection $bankNames)
    {
        $baseQuery->where(function (Builder $builder) use ($bankNames) {
            foreach ($bankNames as $bankName) {
                $builder->where('detail->bank_name', 'like', $bankName, 'or');
            }
        });

        return $baseQuery;
    }

    private function applyWhereNotInBankIdScope(Builder $baseQuery, Collection $bankIds)
    {
        return $baseQuery->whereNotIn('bank_id', $bankIds);
    }

    private function applyWhereInBankIdScope(Builder $baseQuery, Collection $bankIds)
    {
        return $baseQuery->whereIn('bank_id', $bankIds);
    }
}
