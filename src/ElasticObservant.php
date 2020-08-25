<?php

namespace Creatortsv\EloquentElasticSync;

use Illuminate\Support\Facades\Config;

/**
 * Auto boot trait
 */
trait ElasticObservant
{
    /**
     * Elasticsearch configuration
     * @var ElasticIndexConfig
     */
    protected static $elastic;

    /**
     * Adds observer to eloquent models for sync with elasticsearch
     * @return void
     */
    public static function bootElasticObservant(): void
    {
        !Config::get('elastic_sync.disabled', false) && static::observe(new ElasticObserver);
    }

    /**
     * Return Elasticsearch configuration
     * @param bool|null $clear
     * @return ElasticIndexConfig
     */
    protected static function elastic(bool $clear = null): ElasticIndexConfig
    {
        if (self::$elastic === null || ($clear ?? false)) {
            self::$elastic = new ElasticIndexConfig;
        }

        return self::$elastic;
    }
}
