<?php

namespace Creatortsv\EloquentElasticSync\Commands;

use Creatortsv\EloquentElasticSync\ElasticObserver;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Http\Request;
use Psr\Http\Message\ResponseInterface;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

use function GuzzleHttp\json_decode;
use function GuzzleHttp\json_encode;

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
        {--namespace=* : Namespace of eloquent classes}';

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
            $class::boot();

            $this
                ->client
                ->send(new GuzzleRequest(Request::METHOD_DELETE, $class::getElasticIndex(), [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]));

            $bulk = [];
            $index = $class::getElasticIndex();
            $class::all()
                ->each(function (Model $model) use (&$bulk, $index): void {
                    $bulk[] = ['index' => ['_index' => $index, '_id' => $model->getIndexId()]];
                    $bulk[] = ElasticObserver::getData($model);
                });

            if ($bulk) {
                $response = $this
                    ->client
                    ->send(new GuzzleRequest(Request::METHOD_POST, '_bulk', [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                    ], $bulk));

                dd(json_decode($response->getBody(), true));
            }

            $this->info('sync for the class: ' . $class . ' done!');
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
