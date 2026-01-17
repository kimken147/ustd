<?php

namespace App\Providers;

use App\Repository\FeatureToggleRepository;
use Illuminate\Support\ServiceProvider;

class FeatureToggleServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(FeatureToggleRepository::class, function ($app) {
            return new FeatureToggleRepository();
        });
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
