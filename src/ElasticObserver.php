<?php

namespace Creatortsv\EloquentElasticSync;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as AsyncRequest;

/**
 * It helps sync your model to elasticsearch server
 */
class ElasticObserver
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * Constructor
     */
    public function __construct(ClientInterface $client)
    {
    }

    /**
     * @param Model $model
     * @return void
     */
    public function created(Model $model, ClientInterface $client): void
    {
    }

    /**
     * @param Model $model
     * @return void
     */
    public function updating(Model $model): void
    {
    }

    /**
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
    }
}
