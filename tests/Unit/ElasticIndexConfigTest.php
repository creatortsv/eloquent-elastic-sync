<?php

namespace Creatortsv\EloquentElasticSync\Test\Unit;

use Creatortsv\EloquentElasticSync\ElasticIndexConfig;
use Creatortsv\EloquentElasticSync\Test\TestCase;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class ElasticIndexConfigTest extends TestCase
{
    /**
     * @var Model
     */
    protected $model;

    /**
     * @var ElasticIndexConfig
     */
    protected $config;

    /**
     * @return void
     */
    public function setUp(): void
    {
        $this->model = new class ([
            'id' => 1,
            'name' => 'John Smith',
            'email' => 'some@email.com',
        ]) extends Model
        {
            protected $table = 'users';

            protected $fillable = [
                'id',
                'name',
                'email',
            ];

            public function getFullNameAttribute(): string
            {
                return $this->name . ' ' . $this->email;
            }
        };

        $this->model->setRelation('posts', new Collection([new class ([
            'id' => 1,
            'name' => 'Some post',
            'author_id' => 1,
        ]) extends Model
        {
            protected $table = 'posts';

            protected $fillable = [
                'id',
                'name',
                'author_id',
            ];
        }]));

        $this->config = new ElasticIndexConfig(get_class($this->model));
    }

    /**
     * @return void
     */
    public function tearDown(): void
    {
        $this->model = null;
        $this->config = null;
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::connection
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::setConnection
     * @return void
     */
    public function testConfigureConnectionName(): void
    {
        $name = 'my-connection';
        Config::shouldReceive('get')
            ->once()
            ->with('elastic_sync.connection', 'default')
            ->andReturn('default');

        $this->assertNotEquals($name, $this->config->connection());
        $this->assertEquals('default', $this->config->connection());

        $this->config->setConnection($name);
        $this->assertEquals($name, $this->config->connection());
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::index
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::setIndexName
     * @return void
     */
    public function testConfigurationOfElasticIndexName(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with('elastic_sync.indexes.default')
            ->andReturn(null);

        $this->assertNotEmpty($this->config->index(), 'Default index name must be not null');
        $this->assertNotEquals($name = 'some_index', $this->config->index());
        $this->assertEquals($this->model->getTable(), $this->config->index(), 'Default index name must be equal to an eloquent model table name');

        Config::shouldReceive('get')
            ->once()
            ->with('elastic_sync.indexes.default')
            ->andReturn($name);

        $this->assertEquals($name, $this->config->index(), 'Index name must be equal to the "elastyc_sync.indexes.default" config property if it is defined');

        $this->config->setIndexName($name = 'another_index');
        $this->assertEquals($name, $this->config->index(), 'Index name must be equal to the "name" property');
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::addCallback
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::getCallbacks
     * @return void
     */
    public function testAddFieldCallbacks(): void
    {
        $field = 'test';
        $this->assertEmpty($this->config->getCallbacks($field), 'It should be empty by default');

        $this->config->addCallback($field, $func = function (): string {
            return 'some_value';
        });

        $this->config->addCallback($field, $func2 = function (): string {
            return 'some_value';
        });

        $this->assertNotEmpty($this->config->getCallbacks($field), 'It should not be empty by default');
        $this->assertCount(2, $this->config->getCallbacks($field), 'Count of functions should be the same as count of method call for the field name');
        $this->assertEquals([$func, $func2], $this->config->getCallbacks($field));
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::addExtra
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::getExtra
     * @return void
     */
    public function testAddExtraFields(): void
    {
        $this->assertEmpty($this->config->getExtra(), 'It should be empty by default');
        $this->config->addExtra('test', 1);
        $this->assertNotEmpty($this->config->getExtra());
        $this->assertCount(1, $this->config->getExtra());
        $this->assertEquals(['test' => 1], $this->config->getExtra());
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::setMapping
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::execMapping
     * @return void
     */
    public function testExecuteFieldsMapping(): void
    {
        Config::shouldReceive('get')
            ->once()
            ->withArgs(['elastic_sync.indexes.' . $this->model->getTable(), []])
            ->andReturn(null);

        $this->assertEmpty($this->config->execMapping($this->model), 'It should be empty by default');

        Config::shouldReceive('get')
            ->once()
            ->withArgs(['elastic_sync.indexes.' . $this->model->getTable(), []])
            ->andReturn([
                'base_mapping' => [
                    'id',
                    'name',
                ],
                get_class($this->model) => [
                    'some_email' => 'email',
                    'posts' => 'posts.name',
                ],
            ]);

        $mapping = $this->config->execMapping($this->model);
        $this->assertNotEmpty($mapping);
        $this->assertEquals([
            'id' => 'id',
            'name' => 'name',
            'some_email' => 'email',
            'posts' => 'posts.name',
        ], $mapping);

        $mapping = [
            'some_field' => 'some_value',
            'another_field' => 'another_value',
        ];

        $this->config->setMapping(function (Model $model) use ($mapping): array {
            $resutl = array_merge($mapping, [
                'full_name' => 'full_name',
            ]);

            !$model->exists && ($resutl['third_field'] = 'third_value');
            return $resutl;
        });

        $this->assertEquals(array_merge($mapping, [
            'full_name' => 'full_name',
            'third_field' => 'third_value',
        ]), $this->config->execMapping($this->model));
    }
}
