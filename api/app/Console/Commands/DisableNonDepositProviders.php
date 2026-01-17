<?php

namespace App\Console\Commands;

use App\Model\FeatureToggle;
use App\Model\Transaction;
use App\Model\User;
use App\Repository\FeatureToggleRepository;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class DisableNonDepositProviders extends Command
{

    /**
     * @var string
     */
    protected $description = 'Disable non-deposit providers';
    /**
     * @var string
     */
    protected $signature = 'paufen:disable-non-deposit-providers';

    /**
     * @param  FeatureToggleRepository  $featureToggleRepository
     * @return mixed
     */
    public function handle(FeatureToggleRepository $featureToggleRepository)
    {
        if (!$featureToggleRepository->enabled(FeatureToggle::DISABLE_NON_DEPOSIT_PROVIDER)) {
            Log::debug(__CLASS__ . ' feature disabled');
            return;
        }

        Log::debug(__CLASS__ . ' start');

        $now = now();

        User::where([
            'status'       => User::STATUS_ENABLE,
            'role'         => User::ROLE_PROVIDER,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
        ])
            ->whereRaw("TIMESTAMPDIFF(day, created_at, '$now') >= 1")
            ->whereHas('wallet', function (Builder $wallets) {
                $wallets->where('balance', 0);
            })
            ->whereDoesntHave('deposits', function (Builder $deposits) {
                $deposits->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                    ->whereRaw('transactions.created_at >= users.created_at');
            })
            ->update([
                'status'                 => User::STATUS_DISABLE,
                'deposit_enable'         => false,
                'paufen_deposit_enable'  => false,
                'withdraw_enable'        => false,
                'paufen_withdraw_enable' => false,
                'transaction_enable'     => false,
                'ready_for_matching'     => false,
            ]);

        User::where([
            'status'       => User::STATUS_ENABLE,
            'role'         => User::ROLE_PROVIDER,
            'account_mode' => User::ACCOUNT_MODE_GENERAL,
        ])
            ->whereRaw("TIMESTAMPDIFF(day, created_at, '$now') = 1") // 限定只有一天內的新使用者
            ->whereHas('deposits', function (Builder $deposits) {
                $deposits->whereIn('status', [Transaction::STATUS_SUCCESS, Transaction::STATUS_MANUAL_SUCCESS])
                    ->whereRaw('transactions.created_at >= users.created_at');
            })
            ->update([
                'deposit_enable'         => true,
                'paufen_deposit_enable'  => true,
                'withdraw_enable'        => true,
                'paufen_withdraw_enable' => false,
                'transaction_enable'     => true,
                'ready_for_matching'     => false,
            ]);

        Log::debug(__CLASS__ . ' end');
    }
}
