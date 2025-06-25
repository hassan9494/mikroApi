<?php

namespace App\Providers;

use App\Search\ElasticsearchEngine;
use Illuminate\Support\ServiceProvider;
use Laravel\Scout\EngineManager;

class ScoutServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->app->make(EngineManager::class)->extend('elasticsearch', function () {
            return new ElasticsearchEngine();
        });
    }
}
