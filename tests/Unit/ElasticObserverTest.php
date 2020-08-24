<?php

namespace Creatortsv\EloquentElasticSync\Test\Unit;

use Creatortsv\EloquentElasticSync\ElasticObserver;
use Creatortsv\EloquentElasticSync\Test\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;

class ElasticObserverTest extends TestCase
{
	/**
	 * @return void
	 */
	public function testGetModelDataWithDefaultConfiguration(): void
	{
		Config::shouldReceive('get')
			->once()
			->with('elastic_sync.use_mutated_fields')
			->andReturn(false);

		$data = (new ElasticObserver)
			->init($this->model)
			->getData();

		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals($this
			->model
			->only(['id', 'name']), $data, 'Data should be equal to the model attributes');
	}

	/**
	 * @depends testGetModelDataWithDefaultConfiguration
	 * @return void
	 */
	public function testGetModelDataWithMutatedFields(): void
	{
		Config::shouldReceive('get')
			->with('elastic_sync.use_mutated_fields')
			->andReturn(true);

		$data = (new ElasticObserver)
			->init($this->model)
			->getData();

		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals($this
			->model
			->only([
				'id',
				'name',
				'full_name',
			]), $data, 'Data should be equal to the model attributes with mutated attributes');
	}

	/**
	 * @depends testGetModelDataWithMutatedFields
	 * @return void
	 */
	public function testGetModelDataWithConfigMapping(): void
	{
		Config::shouldReceive('get')
			->with('elastic_sync.indexes.' . $this
				->class::elastic()
				->index($this->model->getTable()), [])
			->andReturn([
				$this->class => [
					'id',
					'name' => 'full_name',
					'destination.name',
					'url' => 'template:https://domain.com/[name]/data/?and=[id]',
				],
			]);

		$data = (new ElasticObserver)
			->init($this->model)
			->getData();

		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => $this->model->id,
			'name' => $this->model->full_name,
			'destination.name' => $this->model->destination->name,
			'url' => 'https://domain.com/' . $this->model->name . '/data/?and=' . $this->model->id,
		], $data, 'Data should be equal to config attributes from the model and extra fields');
	}

	/**
	 * @depends testGetModelDataWithConfigMapping
	 * @return void
	 */
	public function testGetModelDataWithExtraFields(): void
	{
		$this
			->class::elastic()
			->addExtra('group', function (Model $model): string {
				return $model->getTable();
			});

		$data = (new ElasticObserver)
			->init($this->model)
			->getData();

		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => $this->model->id,
			'name' => $this->model->full_name,
			'destination.name' => $this->model->destination->name,
			'url' => 'https://domain.com/' . $this->model->name . '/data/?and=' . $this->model->id,
			'group' => $this->model->getTable(),
		], $data, 'Data should be equal to config attributes from the model and extra fields');
	}

	/**
	 * @depends testGetModelDataWithExtraFields
	 * @return void
	 */
	public function testGetModelDataWithModifieredFieldValues(): void
	{
		$this
			->class::elastic()
			->addExtra('group', function (Model $model): string {
				return $model->getTable();
			});

		$this
			->class::elastic()
			->addCallback('name', function (string $value): string {
				return explode('.', $value)[0];
			});

		$this
			->class::elastic()
			->addCallback('name', function (string $value, array $data): string {
				return $value . ' ' . $data['group'];
			});

		$this
			->class::elastic()
			->addCallback('name', function (string $value, array $data, Model $model): string {
				return $value . ' ' . $model->full_name;
			});

		$this
			->class::elastic()
			->addCallback('group', function (string $value, array $data): string {
				return $value . ' ' . $data['name'];
			});

		$result = (new ElasticObserver)
			->init($this->model)
			->getData();

		$this->assertNotEmpty($result, 'Data must not be empty');
		$this->assertEquals([
			'id' => $this->model->id,
			'name' => 'flights flights flights.' . $this->model->name,
			'destination.name' => $this->model->destination->name,
			'url' => 'https://domain.com/' . $this->model->name . '/data/?and=' . $this->model->id,
			'group' => 'flights flights flights flights.' . $this->model->name,
		], $result, 'Data should be equal to config attributes from the model with modifiers');
	}

	/**
	 * @depends testGetModelDataWithModifieredFieldValues
	 * @return void
	 */
	public function testGetModelDataWithCustomMappingAndRelations(): void
	{
		$this
			->class::elastic()
			->setMapping(function (): array {
				return [
					'id',
					'name',
					'destination',
					'destination.name',
				];
			});

		$this
			->class::elastic()
			->addExtra('relations', function (): array {
				return [[
					'some' => 'value',
				]];
			});

		$data = (new ElasticObserver)
			->init($this->model)
			->getData();

		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => $this->model->id,
			'name' => $this->model->name,
			'destination' => $this->model->destination,
			'destination.name' => $this->model->destination->name,
			'relations' => [
				['some' => 'value'],
			],
		], $data, 'Data should be equal to config attributes from the model with modifiers');
	}

	/**
	 * @dataProvider methodsProvider
	 * @param string $through
	 * @return void
	 */
	public function testCallMethodsFromElasticConfig(string $through): Void
	{
		$class = get_class($this->model);
		$observer = new ElasticObserver($class);
		$this->assertTrue(method_exists($class::elastic(), $through));
		$this->assertIsCallable([$observer, $through]);
	}

	/**
	 * @return array
	 */
	public function methodsProvider(): array
	{
		return [
			'Connection method' => ['connection'],
			'Index method' => ['index'],
			'GetCallbacks method' => ['getCallbacks'],
			'GetExtra method' => ['getExtra'],
		];
	}
}
