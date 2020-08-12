<?php

namespace Creatortsv\EloquentElasticSync;

use Illuminate\Support\ServiceProvider;
use GuzzleHttp\{
    Client,
    ClientInterface,
};

/**
 * Service Provider
 */
class EloquentElasticSyncProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app
            ->when(ElasticObserver::class)
            ->needs(ClientInterface::class)
            ->give(function (): Client {
                $conn = config('elastic_sync.connection', 'default');
                $host = config("elastic_sync.connections.$conn.host");
                $port = config("elastic_sync.connections.$conn.port");

                return new Client([
                    'base_uri' => 'http://' . $host . ':' . $port . '/',
                ]);
            });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/config.php' => config_path('elastic_sync.php'),
        ]);
    }
}
