<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{

    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * The path to the "home" route for your application.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //

        parent::boot();
    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapVersioningApiRoutes('v1');

        $this->mapWebRoutes();

        $this->mapVersioningWebRoutes('v1');
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
            ->namespace($this->namespace)
            ->group(base_path('routes/web.php'));
    }

    private function mapVersioningWebRoutes(string $version)
    {
        Route::prefix($version)
            ->middleware('web')
            ->namespace($this->namespace)
            ->as('v1.')
            ->group(base_path("routes/web-$version.php"));
    }

    private function mapVersioningApiRoutes(string $version)
    {
        Route::prefix("api/$version")
            ->middleware('api')
            ->namespace($this->namespace)
            ->group(base_path("routes/api-$version.php"));
    }
}
