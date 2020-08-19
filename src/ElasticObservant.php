<?php

namespace Creatortsv\EloquentElasticSync;

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
        static::observe(new ElasticObserver);
    }

    /**
     * Return Elasticsearch configuration
     * @param bool $clear
     * @return ElasticIndexConfig
     */
    protected static function elastic(bool $clear = false): ElasticIndexConfig
    {
        if (self::$elastic === null || $clear) {
            self::$elastic = new ElasticIndexConfig;
        }

        return self::$elastic;
    }
}
