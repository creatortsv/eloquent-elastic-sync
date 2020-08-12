<?php

namespace Creatortsv\EloquentElasticSync;

trait ElasticObservant
{
    /**
     * Adds observer to eloquent models for sync with elasticsearch
     */
    public static function bootElasticObservant(): void
    {
        static::observe(new ElasticObserver);
    }
}
