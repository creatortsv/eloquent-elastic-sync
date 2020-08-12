<?php

namespace Creatortsv\EloquentElasticSync;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
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
    public function __construct()
    {
        $conn = config('elastic_sync.connection', 'default');
        $host = config("elastic_sync.connections.$conn.host");
        $port = config("elastic_sync.connections.$conn.port");

        $this->client = new Client([
            'base_uri' => 'http://' . $host . ':' . $port . '/',
        ]);
    }

    /**
     * @param Model $model
     * @return void
     */
    public function created(Model $model): void
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
