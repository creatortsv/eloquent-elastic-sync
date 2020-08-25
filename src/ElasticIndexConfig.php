<?php

namespace Creatortsv\EloquentElasticSync;

use Closure;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Needs to setting up an elastic config eloquent model
 */
class ElasticIndexConfig
{
    /**
     * @var string
     */
    protected $connection;

    /**
     * @var Closure
     */
    protected $wrapConnection;

    /**
     * Index name
     * @var string
     */
    protected $name;

    /**
     * ID field of a model value will be used to the index document
     * @var string
     */
    protected $fieldId;

    /**
     * The function which generate fields mapping
     * function (Model $model): array
     * @var Closure
     */
    protected $mapping;

    /**
     * Modifiers of fields mapping result
     * @var array
     */
    protected $fieldCallbacks = [];

    /**
     * Extra fields
     * @var array
     */
    protected $extra = [];

    /**
     * @var Clouser
     */
    protected $query;

    /**
     * Set connection name
     * @param string $connection
     * @return self
     */
    public function setConnection(string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Get connection name
     * 1. From elastic config 
     * 2. From config file
     * @return string
     */
    public function connection(): string
    {
        return $this->connection ?? Config::get('elastic_sync.connection', 'default');
    }

    /**
     * Wrap client connection with callback function
     * @param Closure $callback
     * @return self
     */
    public function wrapConnection(Closure $callback): self
    {
        $this->wrapConnection = $callback;
        return $this;
    }

    /**
     * @param Client $client
     * @return Closure
     */
    public function getWrapCallback(): Closure
    {
        return $this->wrapConnection ?? function (Client $client): Client {
            return $client;
        };
    }

    /**
     * Set field name of the model
     * @param string $field
     * @return string
     */
    public function setFieldId(string $field): self
    {
        $this->fieldId = $field;
        return $this;
    }

    /**
     * Get field name of the model
     * 1. From elastic config 
     * 2. From config file
     * @return string
     */
    public function fieldId(): string
    {
        return $this->fieldId ?? Config::get('elastic_sync.index_id_field', 'id');
    }

    /**
     * Set index name
     * @param string|Closure $name
     * @return self
     */
    public function setIndex($name = null): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get the index name
     * @param string|null $default
     * @return string
     * @throws Exception
     */
    public function index(string $default = null): string
    {
        if (is_string($this->name)) {
            return $this->name;
        }

        $default = Config::get('elastic_sync.indexes.default') ?? $default;

        if (is_callable($this->name)) {
            $name = ($this->name)($default);
        }

        if (is_string($name = $name ?? $default) && strlen($name)) {
            return $name;
        }

        throw new Exception('Return type must be a string!');
    }

    /**
     * Add callback to the field
     * @param string $name
     * @param Closure $callback
     * @return self
     */
    public function addCallback(string $name, Closure $callback): self
    {
        $this->fieldCallbacks[$name][] = $callback;
        return $this;
    }

    /**
     * Get callbacks for the field name
     * @param string $name
     * @return array
     */
    public function getCallbacks(string $name): array
    {
        return $this->fieldCallbacks[$name] ?? [];
    }

    /**
     * Add extra field for the elastic document
     * @param string $name
     * @param mixed|Closure $value
     */
    public function addExtra(string $name, $value): self
    {
        $this->extra[$name] = $value;
        return $this;
    }

    /**
     * Get extra fields
     * @return array
     */
    public function getExtra(): array
    {
        return $this->extra;
    }

    /**
     * Set callback function for the mapping fields
     * @param Closure|null $mapping
     * @return self
     */
    public function setMapping(Closure $mapping = null): self
    {
        $this->mapping = $mapping;
        return $this;
    }

    /**
     * Execute mapping callback function
     * @param Model $model
     * @return array
     */
    public function execMapping(Model $model): array
    {
        if ($this->mapping !== null) {
            return static::createMap(call_user_func($this->mapping, $model));
        }

        $conf = Config::get('elastic_sync.indexes.' . $this->index($model->getTable()), []);
        $maps = array_merge(
            static::createMap($conf['base_mapping'] ?? []),
            static::createMap($conf[get_class($model)] ?? [])
        );

        return $maps;
    }

    /**
     * @param Closure $query
     * @return self
     */
    public function setQuery(Closure $query): self
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @param Client $client
     * @return Closure
     */
    public function getQuery(Builder $query): Builder
    {
        return $this->query
            ? ($this->query)($query)
            : (function (Builder $query): Builder {
                return $query;
            });
    }

    /**
     * @param array map
     * @return array
     */
    public static function createMap(array $map): array
    {
        $data = [];
        foreach ($map as $prop => $alias) {
            $data[is_numeric($prop) ? $alias : $prop] = $alias;
        }

        return $data;
    }
}
