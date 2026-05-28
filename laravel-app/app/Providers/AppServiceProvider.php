<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Push our active-session tracker into the web middleware group so it's executed for web requests.
        if ($this->app->runningInConsole() === false) {
            $router = $this->app->make(\Illuminate\Routing\Router::class);
            $router->pushMiddlewareToGroup('web', \App\Http\Middleware\TrackActiveSession::class);
        }
    }
}
