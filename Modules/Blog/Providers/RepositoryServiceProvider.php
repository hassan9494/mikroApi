<?php

namespace Modules\Blog\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Class RepositoryServiceProvider
 * @package Modules\Blog\Providers
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
            'Modules\Blog\Repositories\Article\ArticleRepositoryInterface',
            'Modules\Blog\Repositories\Article\ArticleRepository'
        );
    }

}
