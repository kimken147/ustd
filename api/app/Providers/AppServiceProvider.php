<?php

namespace App\Providers;

use App\Models\FeatureToggle;
use App\Repository\FeatureToggleRepository;
use App\Services\Transaction\TransactionFeeCalculator;
use App\Services\Transaction\TransactionFeeService;
use App\Utils\BCMathUtil;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(TransactionFeeService::class, function ($app) {
            $featureToggleRepo = $app->make(FeatureToggleRepository::class);
            return new TransactionFeeService(
                $app->make(BCMathUtil::class),
                $featureToggleRepo,
                $featureToggleRepo->enabled(FeatureToggle::CANCEL_PAUFEN_MECHANISM),
                $app->make(TransactionFeeCalculator::class),
            );
        });

        $this->app->bind('evm.adapter.erc20', fn () => new \App\Services\Crypto\Adapters\EvmAdapter('ethereum'));
        $this->app->bind('evm.adapter.bep20', fn () => new \App\Services\Crypto\Adapters\EvmAdapter('bsc'));
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {

        if (env('APP_ENV') && env('APP_ENV') != 'local') {
            URL::forceScheme('https');
        }

    }
}
