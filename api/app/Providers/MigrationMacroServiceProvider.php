<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;

class MigrationMacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * Add backward compatibility for unsignedDecimal which was removed in Laravel 10+
     * MySQL doesn't support unsigned decimals, so we just use decimal instead.
     */
    public function boot(): void
    {
        Blueprint::macro('unsignedDecimal', function (string $column, int $total = 8, int $places = 2) {
            /** @var Blueprint $this */
            return $this->decimal($column, $total, $places);
        });
    }
}
