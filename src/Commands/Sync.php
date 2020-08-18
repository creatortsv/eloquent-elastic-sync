<?php

namespace Creatortsv\EloquentElasticSync\Commands;

use Creatortsv\EloquentElasticSync\ElasticObserver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Artisan command
 */
class Sync extends Command
{
    /**
     * @var string
     */
    protected $signature = 'elastic:sync
        {model?* : Concrete classes of an eloquent model}
        {--namespace=* : Namespace of eloquent classes}
        {--resource=* : Json file with data}';

    /**
     * @var string
     */
    protected $description = 'Sync all of yours eloquent models & elasticsearch indexes';

    /**
     * @var ClientInterface
     */
    protected $client;

    /**
     * Constructor
     * @param ClientInterface $client
     */
    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $namespsArg = array_map([static::class, 'normalize'], $this->option('namespace'));
        $classesArg = array_map([static::class, 'normalize'], $this->argument('model'));
        $classes = [];

        /**
         * Get exists class with ElasticObservant trait
         * 1. Check class from the model argument
         * 2. Check class from the model argument with namespaces from the option namespace
         */
        foreach ($classesArg as $class) {
            if (class_exists($class) && method_exists($class, 'bootElasticObservant')) {
                $classes[] = $class;
            }

            foreach ($namespsArg as $name) {
                if (class_exists($class = $name . '\\' . $class) && method_exists($class, 'bootElasticObservant')) {
                    $classes[] = $class;
                }
            }
        }

        foreach ($classes as $class) {
            $this->info('Start sync for the class: ' . $class);
            $class::booted();

            try {
                $this
                    ->client
                    ->send(new GuzzleRequest(Request::METHOD_DELETE, $index = $class::elastic()->index(), [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ]));
            } catch (GuzzleException $e) {
            }

            $bulk = [];
            $class::all()
                ->each(function (Model $model) use (&$bulk, $index): void {
                    $data = ElasticObserver::getData($model);
                    $bulk[] = json_encode(['index' => ['_index' => $index, '_id' => $data[Config::get('elastic_sync.indexes.index_id_field', 'id')]]]);
                    $bulk[] = json_encode($data);
                });

            if ($bulk) {
                $this
                    ->client
                    ->send(new GuzzleRequest(Request::METHOD_POST, '_bulk', [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ], implode("\n", $bulk) . "\n"));
            }

            $this->info('Sync for the class: ' . $class . ' done!');
        }

        foreach ($this->option('resource') as $src) {
            if (!file_exists($src)) {
                $this->error('File not found: ' . $src);
            };

            $this->info('Start sync for the file: ' . $src);
            $response = $this
                ->client
                ->send(new GuzzleRequest(Request::METHOD_POST, '_bulk', [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ], file_get_contents($src)));

            $this->info('Sync for the file: ' . $src . ' done!');
        }
    }

    /**
     * @var string
     * @return string
     */
    protected static function normalize(string $str): string
    {
        return str_replace('/', '\\', $str);
    }
}
