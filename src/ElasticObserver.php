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
use Illuminate\Support\Facades\Event;
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
     * @var Model
     */
    protected $model;

    /**
     * @var array
     */
    protected $data;

    /**
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        if (method_exists($this, $name)) {
            return $this->$name(...$arguments);
        }

        if (method_exists($this->class::elastic(), $name)) {
            return $this->class::elastic()->$name(...$arguments);
        }
    }

    /**
     * Required method before doing some action
     * @param Model $model
     * @return ElasticObserver
     */
    public function init(Model $model): self
    {
        $this->class = get_class($model);
        $this->model = $model;
        $this->data = $this->getData();
        return $this;
    }

    /**
     * @param Model $model
     * @return void
     */
    public function saved(Model $model): void
    {
        $this
            ->init($model)
            ->async(...$this->requestArguments(Request::METHOD_PUT))
            ->wait();
    }

    /**
     * @param Model $model
     * @return void
     */
    public function deleted(Model $model): void
    {
        $this
            ->init($model)
            ->async(...$this->requestArguments(Request::METHOD_DELETE, false))
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
        switch ($method) {
            case Request::METHOD_PUT:
            case Request::METHOD_POST:
                $type = 'saved';
                break;
            default:
                $type = 'deleted';
        }

        return $this
            ->client()
            ->sendAsync(new AsyncRequest($method, $uri, $headers ?: [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ], json_encode($body)))
            ->then(function (ResponseInterface $response) use ($type): void {
                ($event = Config::get('elastic_sync.events.' . $type)) && Event::fire(new $event($response));
            }, function (RequestException $e): void {
                ($event = Config::get('elastic_sync.events.failed')) && Event::fire(new $event($e));
            });
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        $maps = $this->execMapping($this->model);
        $data = [];

        /** 1. Create data from the mapping */
        if (!$maps) {
            $data = $this
                ->model
                ->getAttributes();

            if (Config::get('elastic_sync.use_mutated_fields')) {
                $data = array_merge($data, array_combine($mutated = $this->model->getMutatedAttributes(), array_map((function (string $attr) {
                    return $this->model->$attr;
                })->bindTo($this), $mutated)));
            }
        } else {
            $collect = collect([$this->model]);
            foreach ($maps as $prop => $alias) {
                if (strpos($alias, 'template:') !== false) {
                    preg_match_all("/\[([^\]]*)\]/", $alias = str_replace('template:', '', $alias), $matches);
                    foreach ($matches[1] ?? [] as $sub) {
                        $alias = str_replace('[' . $sub . ']', $collect
                            ->pluck($sub)
                            ->first(), $alias);
                    }

                    $value = $alias;
                } else {
                    $value = $collect
                        ->pluck($alias)
                        ->first();
                }

                $data[$prop] = $value;
            }

            unset($collect);
        }

        /** 2. Add extra fields */
        foreach ($this->getExtra() as $prop => $value) {
            if (!isset($data[$prop])) {
                $data[$prop] = is_callable($value)
                    ? $value($this->model)
                    : $value;
            }
        }

        /** 3. Apply callbacks to fields */
        foreach ($data as $prop => &$value) {
            foreach ($this->getCallbacks($prop) as $callback) {
                $value = $callback($value, $data, $this->model);
            }

            $data[$prop] = $value;
        }

        return $data;
    }

    /**
     * @param string $method
     * @return array
     */
    protected function requestArguments(string $method, bool $useBody = true): array
    {
        $prop = $this->fieldId();
        $guid = $this->data[$prop] ?? null;
        $args = [$method];

        if (!$guid) {
            throw new Exception('Data must contain the "' . $prop . '" property');
        }

        array_push($args, ($this->index() ?? $this->model->getTable()) . '/_doc/' . $guid);
        $useBody && array_push($args, $this->data);

        return $args;
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

        return $this->getWrapCallback()(new Client([
            'base_uri' => 'http://' . $host . ':' . $port . '/',
        ]));
    }
}
