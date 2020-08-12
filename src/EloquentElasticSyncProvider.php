<?php

namespace Creatortsv\EloquentElasticSync;

use Illuminate\Support\ServiceProvider;

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
