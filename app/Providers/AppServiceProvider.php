<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'App\Repositories\User\UserRepositoryInterface',
            'App\Repositories\User\UserRepository'
        );
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
        if($this->app->environment('production'))
        {
            \URL::forceScheme('https');
        }
    }
}
