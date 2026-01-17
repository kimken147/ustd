<?php

namespace App\Console\Commands;

use App\Model\FeatureToggle;
use App\Model\User;
use App\Repository\FeatureToggleRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DisableNonLoginUser extends Command
{

    /**
     * @var string
     */
    protected $description = '關閉未登入碼商';
    /**
     * @var string
     */
    protected $signature = 'paufen:disable-non-login-user';

    public function handle(FeatureToggleRepository $featureToggleRepository)
    {
        if (!$featureToggleRepository->enabled(FeatureToggle::AUTO_DISABLE_NON_LOGIN_USER)) {
            Log::debug(__CLASS__.' feature disabled');

            return;
        }

        Log::debug(__CLASS__.' start');

        $now = now();

        User::where([
            'status' => User::STATUS_ENABLE,
            'role'   => User::ROLE_PROVIDER,
        ])
            ->whereRaw("TIMESTAMPDIFF(day, last_login_at, '$now') >= 3")
            ->update([
                'status'             => User::STATUS_DISABLE,
                'ready_for_matching' => false,
                'updated_at'         => $now,
            ]);

        User::where([
            'status'        => User::STATUS_ENABLE,
            'role'          => User::ROLE_PROVIDER,
            'last_login_at' => null,
        ])
            ->whereRaw("TIMESTAMPDIFF(day, created_at, '$now') >= 3")
            ->update([
                'status'             => User::STATUS_DISABLE,
                'ready_for_matching' => false,
                'updated_at'         => $now,
            ]);

        Log::debug(__CLASS__.' end');
    }
}
