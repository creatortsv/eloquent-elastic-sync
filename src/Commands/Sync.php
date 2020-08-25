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
        $this->info(PHP_EOL . '[ *** Eloquent Model with Elasticsearch syncronization command *** ]' . PHP_EOL);
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

        empty($classes)
            ? $this->error('Classes not found!' . PHP_EOL)
            : $this->info('Next classes will be syncronized:' . PHP_EOL);

        array_walk($classes, (function (string $class): void {
            $this->line($class);
        })->bindTo($this)) && $this->info('');

        foreach ($classes as $class) {
            $class::boot();
            $this->info('*** Prepare syncronization for the class ' . $class . ' ***' . PHP_EOL);
            $this->line('Host: ' . Config::get('elastic_sync.connection.' . $class::elastic()->connection() . '.host', 'localhost'));
            $this->line('Port: ' . Config::get('elastic_sync.connection.' . $class::elastic()->connection() . '.port', 9200));
            $this->line('Index name: ' . $index = $class::elastic()->index((new $class)->getTable()) . PHP_EOL);

            try {
                json_decode($this
                    ->client
                    ->send(new GuzzleRequest(Request::METHOD_DELETE, $index, [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ]))
                    ->getBody(), true);

                $this->info('Index name: ' . $index . ' has been deleted' . PHP_EOL);
            } catch (GuzzleException $e) {
            }

            $this->info('Start syncronisation for ' . ($count = $class::count()) . ' items ... ' . PHP_EOL);

            $progress = $this->output->createProgressBar($count);
            $progress->start();
            $class::chunk(10, function (Collection $collection) use ($progress): void {
                $bulk = [];
                $collection->each(function (Model $model) use (&$bulk, $progress): void {
                    $observer = new ElasticObserver;
                    $observer->init($model);

                    $data = $observer->getData($model);
                    $bulk[] = json_encode(['index' => ['_index' => $observer->index($model->getTable()), '_id' => $data[$observer->fieldId()]]]);
                    $bulk[] = json_encode($data);
                    $progress->advance();
                });

                if ($bulk) {
                    $this
                        ->client
                        ->send(new GuzzleRequest(Request::METHOD_POST, '_bulk', [
                            'Content-Type' => 'application/json',
                            'Accept' => 'application/json',
                        ], implode("\n", $bulk) . "\n"));
                }
            });

            $progress->finish();
            $this->line(PHP_EOL);
            $this->info('*** Syncronization for the class ' . $class . ' completed! ***' . PHP_EOL);
        }

        foreach ($this->option('resource') as $src) {
            $this->info('*** Start syncronization with resource: ' . $src . ' ***' . PHP_EOL);
            if (!file_exists($src)) {
                $this->error('File not found: ' . $src);
            };

            $this
                ->client
                ->send(new GuzzleRequest(Request::METHOD_POST, '_bulk', [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ], file_get_contents($src)));

            $this->info('*** Syncronization with resource: ' . $src . ' completed! ***' . PHP_EOL);
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
