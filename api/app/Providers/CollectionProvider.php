<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Collection;

class CollectionProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Collection::macro('sumMultiple', function ($columns) {
            $result = [];
            foreach ($columns as $column) {
                $result[$column] = 0;
            }

            return $this->reduce(function ($result, $item) use ($columns) {
                foreach ($columns as $column) {
                    if (!isset($result[$column])) {
                        $result[$column] = 0;
                    }
                    $result[$column] += $item[$column];
                }

                return $result;
            }, $result);
        });
    }
}
