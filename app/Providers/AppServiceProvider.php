<?php

namespace App\Providers;

use Elastic\Elasticsearch\ClientBuilder;
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
        $this->app->singleton('custom-elastic', function() {
            return new \App\Services\ElasticsearchService();
        });
        $this->app->singleton('elasticsearch', function() {
            $host = config('scout.elasticsearch.host');

            // Validate and format host
            if (!parse_url($host, PHP_URL_SCHEME)) {
                $host = 'http://' . $host;
            }

            // Add port if missing
            if (!parse_url($host, PHP_URL_PORT) && parse_url($host, PHP_URL_SCHEME) === 'http') {
                $host .= ':9200';
            }

            return ClientBuilder::create()
                ->setHosts([$host])
                ->setBasicAuthentication(
                    config('scout.elasticsearch.user'),
                    rawurlencode(config('scout.elasticsearch.password'))
                )
                ->setSSLVerification(false)
                ->build();
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Log Elasticsearch configuration on every request
//        \Log::debug('Elasticsearch Configuration', [
//            'host' => config('scout.elasticsearch.host'),
//            'user' => config('scout.elasticsearch.user'),
//            'index' => config('scout.elasticsearch.index'),
//            'password_length' => strlen(config('scout.elasticsearch.password'))
//        ]);
        //
        if($this->app->environment('production'))
        {
            \URL::forceScheme('https');
        }
    }
}
