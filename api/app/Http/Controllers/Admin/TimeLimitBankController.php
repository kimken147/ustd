<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\TimeLimitBankGroupCollection;
use App\Console\Commands\DisableTimeLimitUserChannelAccount;
use App\Model\FeatureToggle;
use App\Model\Permission;
use App\Model\TimeLimitBank;
use App\Model\Bank;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Artisan;

class TimeLimitBankController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:'.Permission::ADMIN_MANAGE_TIME_LIMIT_BANK]);
    }

    public function batchDestroy(Request $request)
    {
        $this->validate($request, [
            'bank_name' => 'required',
        ]);

        TimeLimitBank::where('bank_name', $request->input('bank_name'))->delete();

        Artisan::call('paufen:disable-time-limit-user-channel-account', [
            'user_channel_account' => null
        ]);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function destroy(TimeLimitBank $timeLimitBank)
    {
        $timeLimitBank->delete();

        Artisan::call('paufen:disable-time-limit-user-channel-account', [
            'user_channel_account' => null
        ]);

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    public function index(Request $request)
    {
        $timeLimitBanks = TimeLimitBank::has('bank')->groupBy('bank_id')->orderBy('bank_id')->with([
            'timeLimitBanks' => function (HasMany $timeLimitBanks) {
                return $timeLimitBanks->orderBy('started_at');
            }
        ], 'bank')->select('bank_id');

        $timeLimitBanks->when($request->filled('bank_name'), function (Builder $timeLimitBanks) use ($request) {
            $timeLimitBanks->whereHas('bank', function ($hasTimeLimitBanks) use ($request) {
                $hasTimeLimitBanks->where('name', 'like', "%{$request->bank_name}%");
            });
        });

        $lateTimeBankLimitFeature = FeatureToggle::findOrFail(FeatureToggle::LATE_NIGHT_BANK_LIMIT);

        return TimeLimitBankGroupCollection::make($timeLimitBanks->paginate(20))->additional([
            'meta' => [
                'late_night_bank_limit_feature' => \App\Http\Resources\Admin\FeatureToggle::make($lateTimeBankLimitFeature),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'status'     => ['required', Rule::in(TimeLimitBank::STATUS_DISABLE, TimeLimitBank::STATUS_ENABLE)],
            'bank_id'    => ['required', 'numeric'],
            'started_at' => 'date_format:H:i:s',
            'ended_at'   => 'date_format:H:i:s',
        ]);

        $bankData = Bank::find($request->input('bank_id'));
        abort_if(empty($bankData), Response::HTTP_BAD_REQUEST, '銀行設定錯誤');

        $timeLimitBank = TimeLimitBank::create([
            'status'     => $request->input('status'),
            'bank_id'    => $bankData->getKey(),
            'bank_name'  => $bankData->name,
            'started_at' => $request->input('started_at'),
            'ended_at'   => $request->input('ended_at'),
        ]);

        Artisan::call('paufen:disable-time-limit-user-channel-account', [
            'user_channel_account' => null
        ]);

        return \App\Http\Resources\Admin\TimeLimitBank::make($timeLimitBank);

    }

    public function update(Request $request, TimeLimitBank $timeLimitBank)
    {
        $this->validate($request, [
            'status'     => ['required', Rule::in(TimeLimitBank::STATUS_DISABLE, TimeLimitBank::STATUS_ENABLE)],
            'started_at' => 'date_format:H:i:s',
            'ended_at'   => 'date_format:H:i:s',
        ]);

        $timeLimitBank->update([
            'status'     => $request->input('status'),
            'started_at' => $request->input('started_at'),
            'ended_at'   => $request->input('ended_at'),
        ]);

        Artisan::call('paufen:disable-time-limit-user-channel-account', [
            'user_channel_account' => null
        ]);

        return \App\Http\Resources\Admin\TimeLimitBank::make($timeLimitBank);
    }
}
