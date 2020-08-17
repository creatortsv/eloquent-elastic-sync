<?php

namespace Creatortsv\EloquentElasticSync;

use Exception;
use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as AsyncRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Psr\Http\Message\ResponseInterface;

/**
 * It helps sync your model to elasticsearch server
 */
class ElasticObserver
{
    /**
     * @var string
     */
    protected $class;

    /**
     * Constructor
     */
    public function __construct(string $config)
    {
        $this->class = $config;
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this->class::elastic(), $name)) {
            return $this->class::elastic()->$name(...$arguments);
        }

        if (method_exists($this, $name)) {
            return $this->$name(...$arguments);
        }
    }

    /**
     * @param Model $model
     * @return void
     */
    public function saved(Model $model): void
    {
        $this
            ->async(Request::METHOD_PUT, ...$this->uri($model))
            ->then(function (ResponseInterface $response): void {
                /** TODO: event */
            }, function (RequestException $e): void {
                /** TODO: event */
            })
            ->wait();
    }

    /**
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        $this
            ->async(Request::METHOD_DELETE, $this->uri($model)[0])
            ->then(function (ResponseInterface $response): void {
                /** TODO: event */
            }, function (RequestException $e): void {
                /** TODO: Exception */
            })
            ->wait();
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $body
     * @param array $headers
     * @return PromiseInterface
     */
    protected function async(
        string $method,
        string $uri,
        array $body = [],
        array $headers = []
    ): PromiseInterface {
        return $this
            ->client()
            ->sendAsync(new AsyncRequest($method, $uri, $headers ?: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], json_encode($body)));
    }

    /**
     * @param Model $model
     * @return array
     */
    public static function getData(Model $model): array
    {
        $class = get_class($model);
        $maps = $class::elastic()->execMapping($model);
        $data = [];

        /** 1. Create data from the mapping */
        if (!$maps) {
            $data = $model->getAttributes();
            Config::get('elastic_sync.use_mutated_fields') && ($data = array_merge(
                $data,
                array_combine($model->getMutatedAttributes(), array_map(function (string $attr) use ($model) {
                    return $model->$attr;
                }, $model->getMutatedAttributes())),
            ));
        } else {
            foreach ($maps as $prop => $alias) {
                $value = collect([$model])
                    ->pluck($alias)
                    ->first();

                $data[$prop] = $value;
            }
        }

        /** 2. Add extra fields */
        foreach ($class::elastic()->getExtra() as $prop => $value) {
            if (!isset($data[$prop])) {
                $data[$prop] = is_callable($value)
                    ? $value($model)
                    : $value;
            }
        }

        /** 3. Apply callbacks to fields */
        foreach ($data as $prop => &$value) {
            foreach ($class::elastic()->getCallbacks($prop) as $callback) {
                $value = $callback($value, $data, $model);
            }

            $data[$prop] = $value;
        }

        return $data;
    }

    /**
     * @param Model $model
     * @return array
     */
    protected function uri(Model $model): array
    {
        $idProp = Config::get('elastic_sync.indexes.index_id_field', 'id');
        $data = self::getData($model);
        $guid = $data[$idProp] ?? null;

        if (!$guid) {
            throw new Exception('Data must contain the "' . $idProp . '" property');
        }

        return [
            $this->index() . '/_doc/' . $guid,
            $data,
        ];
    }

    /**
     * @param Model $model
     * @return Client
     */
    protected function client(): Client
    {
        $conn = $this->connection();
        $host = Config::get("elastic_sync.connections.$conn.host");
        $port = Config::get("elastic_sync.connections.$conn.port");

        return new Client([
            'base_uri' => 'http://' . $host . ':' . $port . '/',
        ]);
    }
}
