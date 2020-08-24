<?php

namespace Creatortsv\EloquentElasticSync\Test\Unit;

use Creatortsv\EloquentElasticSync\ElasticIndexConfig;
use Creatortsv\EloquentElasticSync\Test\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class ElasticIndexConfigTest extends TestCase
{
    /**
     * @return array
     */
    public function configurationProvider(): array
    {
        return [
            'connection option' => ['elastic_sync.connection', 'default', 'connection'],
            'field id name option' => ['elastic_sync.index_id_field', 'id', 'fieldId'],
            'index name option' => ['elastic_sync.indexes.default', null, 'index', 'some_table'],
        ];
    }

    /**
     * @dataProvider configurationProvider
     * @param string $config
     * @param string|null $default
     * @param string $method
     * @param string $arg
     * @return void
     */
    public function testDefaultOptionConfiguration(string $config, string $default = null, string $method, string $arg = null): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with(...array_filter([$config, $default]))
            ->andReturn($default);

        $option = $this
            ->class::elastic()
            ->$method($arg);

        $this->assertNotEmpty($option, 'It should not be empty by default');
        $this->assertEquals($arg ?? $default, $option, 'It should be equal to "' . $default . '" by default');
    }

    /**
     * @dataProvider configurationProvider
     * @depends testDefaultOptionConfiguration
     * @param string $config
     * @param string|null $default
     * @param string $method
     * @return void
     */
    public function testChangedOptionConfigurationByConfig(string $config, string $default = null, string $method): void
    {
        Config::shouldReceive('get')
            ->once()
            ->with(...array_filter([$config, $default]))
            ->andReturn($name = 'changed');

        $option = $this
            ->class::elastic()
            ->$method();

        $this->assertNotEmpty($option, 'It should not be empty');
        $this->assertEquals($name, $option, 'It should be equal to "' . $name . '"');
    }

    /**
     * @dataProvider configurationProvider
     * @depends testChangedOptionConfigurationByConfig
     * @param string $config
     * @param string|null $default
     * @param string $method
     * @return void
     */
    public function testAssignedOptionConfiguration(string $config, string $default = null, string $method): void
    {
        $this
            ->class::elastic()
            ->{'set' . Str::ucfirst($method)}($name = 'assigned');

        $option = $this
            ->class::elastic()
            ->$method();

        $this->assertNotEmpty($option, 'It should not be empty');
        $this->assertEquals($name, $option, 'It should be equal to "' . $name . '"');
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::wrapConnection
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::getWrapCallback
     * @return void
     */
    public function testUsingWrapConnectionCallback(): void
    {
        $callback = $this
            ->class::elastic()
            ->getWrapCallback();

        $this->assertTrue(is_callable($callback), 'It should be callable by default');
        $this->assertEquals($callback(new Client), new Client);

        $this
            ->class::elastic()
            ->wrapConnection(function (): Client {
                return new Client(['base_uri' => 'http://test.com/api']);
            });

        $callback = $this
            ->class::elastic()
            ->getWrapCallback();

        $this->assertTrue(is_callable($callback), 'It should be callable');
        $this->assertNotEquals($callback(new Client), new Client);
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::addCallback
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::getCallbacks
     * @return void
     */
    public function testAddFieldCallbacks(): void
    {
        $this->assertEmpty($this
            ->class::elastic()
            ->getCallbacks($field = 'test'), 'It should be empty by default');

        $this
            ->class::elastic()
            ->addCallback($field, function (): string {
                return 'value 1';
            });

        $this
            ->class::elastic()
            ->addCallback($field, function (): string {
                return 'value 2';
            });

        $this->assertNotEmpty($this
            ->class::elastic()
            ->getCallbacks($field), 'It should not be empty by default');

        $this->assertCount(2, $callbacks = $this
            ->class::elastic()
            ->getCallbacks($field), 'Count of functions should be the same as count of method call for the field name');

        foreach ($callbacks as $i => $callback) {
            $this->assertTrue(is_callable($callback));
            $this->assertEquals('value ' . (string)($i + 1), $callback());
        }
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::addExtra
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::getExtra
     * @return void
     */
    public function testAddExtraFields(): void
    {
        $this->assertEmpty($this
            ->class::elastic()
            ->getExtra(), 'It should be empty by default');

        $this
            ->class::elastic()
            ->addExtra('one', 1);

        $this
            ->class::elastic()
            ->addExtra('two', function (): int {
                return 2;
            });

        $this->assertNotEmpty($this
            ->class::elastic()
            ->getExtra());

        $this->assertCount(2, $this
            ->class::elastic()
            ->getExtra());

        $extra = $this
            ->class::elastic()
            ->getExtra();

        $this->assertEquals([
            'one' => 1,
            'two' => 2,
        ], array_map(function ($val): int {
            return is_callable($val) ? $val() : $val;
        }, $extra));
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::execMapping
     * @return string
     */
    public function testGetDefaultFieldsOfMapping(): string
    {
        $index = $this
            ->model
            ->getTable();

        $this
            ->class::elastic()
            ->setIndex($index);

        Config::shouldReceive('get')
            ->once()
            ->with('elastic_sync.indexes.' . $index, [])
            ->andReturn(null);

        $this->assertEmpty($this
            ->class::elastic()
            ->execMapping($this->model), 'It should be empty by default');

        return $index;
    }

    /**
     * @depends testGetDefaultFieldsOfMapping
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::execMapping
     * @param string $index
     * @return array
     */
    public function testGetChangedFieldsOfMappingByConfig(string $index): array
    {
        Config::shouldReceive('get')
            ->once()
            ->with('elastic_sync.indexes.' . $index, [])
            ->andReturn([
                'base_mapping' => [
                    'id',
                    'name',
                ],
                $this->class => [
                    'name' => 'full_name',
                    'post' => 'post.name',
                ],
            ]);

        $mapping = $this
            ->class::elastic()
            ->execMapping($this->model);

        $this->assertNotEmpty($mapping, 'It should be not empty');
        $this->assertEquals([
            'id' => 'id',
            'name' => 'full_name',
            'post' => 'post.name',
        ], $mapping);

        return $mapping;
    }

    /**
     * @depends testGetChangedFieldsOfMappingByConfig
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::setMapping
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::execMapping
     * @param array $mapping
     * @return void
     */
    public function testGetAssignedFieldsOfMapping(array $mapping): void
    {
        $this
            ->class::elastic()
            ->setMapping(function () use ($mapping): array {
                return array_merge($mapping, [
                    'one' => 'thirst',
                    'two' => 'second',
                ]);
            });

        $this->assertEquals(array_merge($mapping, [
            'one' => 'thirst',
            'two' => 'second',
        ]), $this
            ->class::elastic()
            ->execMapping($this->model));
    }

    /**
     * @covers Creatortsv\EloquentElasticSync\ElasticIndexConfig::createMap
     * @return void
     */
    public function testCreatingMapData(): void
    {
        $this->assertEquals([
            'id' => 'id',
            'name' => 'full_name',
        ], ElasticIndexConfig::createMap([
            'id',
            'name' => 'full_name',
        ]));
    }
}
