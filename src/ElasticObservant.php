<?php

namespace Creatortsv\EloquentElasticSync;

use Closure;

/**
 * Auto boot trait
 */
trait ElasticObservant
{
    /**
     * @var ElasticConfig
     */
    protected static $elasticConfig;

    /**
     * Adds observer to eloquent models for sync with elasticsearch
     */
    public static function bootElasticObservant(): void
    {
        static::observe(new ElasticObserver(self::class));
    }

    /**
     * @return ElasticIndexConfig
     */
    protected static function elastic(): ElasticIndexConfig
    {
        if (self::$elasticConfig === null) {
            self::$elasticConfig = new ElasticIndexConfig(self::class);
        }

        return self::$elasticConfig;
    }
}
