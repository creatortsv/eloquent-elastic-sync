<?php

namespace Creatortsv\EloquentElasticSync;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Needs to setting up an elastic config eloquent model
 */
class ElasticIndexConfig
{
    /**
     * Class of an eloquent model
     * @var string
     */
    protected $class;

    /**
     * Index name
     * @var string
     */
    protected $name;

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
     * Constructor
     */
    public function __construct(string $class)
    {
        $this->class = $class;
    }

    /**
     * Set index name
     * @param string $name
     * @return self
     */
    public function setIndexName(string $name): self
    {
        $this->name = $name;
        return $this;
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
     * Get the index name
     * @return string
     */
    public function index(): string
    {
        return $this->name ?? Config::get('elastic_sync.indexes.default') ?? (new $this->class)->getTable();
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
     * Get extra fields
     * @return array
     */
    public function getExtra(): array
    {
        return $this->extra;
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

        $conf = Config::get('elastic_sync.indexes.' . $this->index(), []);
        $maps = array_merge(
            static::createMap($conf['base_mapping'] ?? []),
            static::createMap($conf[$this->class] ?? []),
        );

        return $maps;
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
