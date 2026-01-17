<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\FeatureToggleCollection;
use App\Console\Commands\DisableTimeLimitUserChannelAccount;
use App\Models\FeatureToggle;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Artisan;

class FeatureToggleController extends Controller
{

    public function __construct()
    {
        $this->middleware(['permission:'.Permission::ADMIN_UPDATE_FEATURE_TOGGLE])->only('update');
    }

    public function index()
    {
        return FeatureToggleCollection::make(FeatureToggle::where('hidden', false)->get());
    }

    public function update(FeatureToggle $featureToggle, Request $request)
    {
        $this->validate($request, [
            'enabled' => 'boolean',
        ]);

        switch ($featureToggle->getInput('type')) {
            case FeatureToggle::INPUT_TYPE_TEXT:
                $this->validate($request, [
                    'value' => 'nullable|string|max:20',
                ]);

                $featureToggle->update([
                    'enabled' => $request->input('enabled', $featureToggle->enabled),
                    'input'   => [
                        'type'  => FeatureToggle::INPUT_TYPE_TEXT,
                        'unit'  => $featureToggle->getInput('unit'),
                        'value' => $request->input('value') ?? $featureToggle->getInput('value'),
                    ]
                ]);

                break;

            case FeatureToggle::INPUT_TYPE_BOOLEAN:

                $featureToggle->update([
                    'enabled' => $request->input('enabled', $featureToggle->enabled),
                    'input'   => [
                        'type'  => FeatureToggle::INPUT_TYPE_BOOLEAN,
                        'unit'  => $featureToggle->getInput('unit'),
                        'value' => $request->input('value') ?? $featureToggle->getInput('value'),
                    ]
                ]);

                if ($featureToggle->getKey() === FeatureToggle::LATE_NIGHT_BANK_LIMIT) {
                    Artisan::call('paufen:disable-time-limit-user-channel-account', [
                        'user_channel_account' => null
                    ]);
                }

                if ($featureToggle->getKey() === FeatureToggle::EXCHANGE_MODE && !$featureToggle->enabled) {
                    User::query()->update(['exchange_mode_enable' => false]);
                }

                break;
            default:
                abort(Response::HTTP_BAD_REQUEST);
        }

        return \App\Http\Resources\Admin\FeatureToggle::make($featureToggle);
    }
}
