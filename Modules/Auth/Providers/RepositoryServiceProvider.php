<?php

namespace Modules\Auth\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class RepositoryServiceProvider
 * @package Modules\Other\Providers
 */
class RepositoryServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
    }
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(
            'Modules\Auth\Repositories\Role\RoleRepository',
            'Modules\Auth\Repositories\Role\EloquentRole'
        );
    }

}
