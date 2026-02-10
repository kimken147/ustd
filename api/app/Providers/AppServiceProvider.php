<?php

namespace App\Providers;

use App\Models\FeatureToggle;
use App\Repository\FeatureToggleRepository;
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
            );
        });
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
