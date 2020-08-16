<?php

namespace Creatortsv\EloquentElasticSync;

use Illuminate\Support\ServiceProvider;
use Creatortsv\EloquentElasticSync\Commands\Sync;
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
            ->when(Sync::class)
            ->needs(ClientInterface::class)
            ->give(function (): ClientInterface {
                $conn = config('elastic_sync.connection', 'default');
                $host = config("elastic_sync.connections.$conn.host");
                $port = config("elastic_sync.connections.$conn.port");

                return new Client([
                    'base_uri' => 'http://' . $host . ':' . $port . '/',
                ]);
            });

        $this->mergeConfigFrom(__DIR__ . '/config.php', 'elastic_sync');
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                Sync::class,
            ]);
        }
    }
}
