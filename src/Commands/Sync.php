<?php

namespace Creatortsv\EloquentElasticSync\Commands;

use Creatortsv\EloquentElasticSync\ElasticObserver;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Request;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
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
                $response = json_decode($this
                    ->client
                    ->send(new GuzzleRequest(Request::METHOD_DELETE, $index = $class::elastic()->index(), [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ]))
                    ->getBody(), true);

                $this->info('Delete index: ' . $class . ' done!');
            } catch (GuzzleException $e) {
                $this->info('Nothing to delete with index name ' . $index);
            }

            $this->info('Build data of the ' . $class . ' items');
            $bulk = [];
            $progress = $this->output->createProgressBar($class::count());
            $progress->start();
            $class::chunk(250, function (Collection $collection) use (&$bulk, $index, $progress): void {
                $collection->each(function (Model $model) use (&$bulk, $index, $progress): void {
                    $data = (new ElasticObserver)
                        ->init($model)
                        ->getData($model);

                    $bulk[] = json_encode(['index' => ['_index' => $index, '_id' => $data[Config::get('elastic_sync.indexes.index_id_field', 'id')]]]);
                    $bulk[] = json_encode($data);
                    $progress->advance();
                });
            });

            $progress->finish();

            if ($bulk) {
                $this
                    ->client
                    ->send(new GuzzleRequest(Request::METHOD_POST, '_bulk', [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ], implode("\n", $bulk) . "\n"));

                $this->info('Sync for the class: ' . $class . ' done!');
            } else {
                $this->info('Nothing to index with the class ' . $class);
            }
        }

        foreach ($this->option('resource') as $src) {
            $this->info('Start sync with resource: ' . $src);
            if (!file_exists($src)) {
                $this->error('File not found: ' . $src);
            };

            $response = $this
                ->client
                ->send(new GuzzleRequest(Request::METHOD_POST, '_bulk', [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ], file_get_contents($src)));

            $this->info('Sync with resource: ' . $src . ' done!');
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
