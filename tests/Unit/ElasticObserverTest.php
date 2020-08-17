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
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::getData
	 */
	public function testGetModelDataWithDefaultConfiguration(): void
	{
		Config::shouldReceive('get')
			->once()
			->with('elastic_sync.use_mutated_fields')
			->andReturn(false);

		$data = ElasticObserver::getData($this->model);
		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => 1,
			'name' => 'John Smith',
			'email' => 'some@email.com',
		], $data, 'Data should be equal to the model attributes');
	}

	/**
	 * @depends testGetModelDataWithDefaultConfiguration
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::getData
	 * @return void
	 */
	public function testGetModelDataWithMutatedFields(): void
	{
		Config::shouldReceive('get')
			->with('elastic_sync.use_mutated_fields')
			->andReturn(true);

		$data = ElasticObserver::getData($this->model);
		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => 1,
			'name' => 'John Smith',
			'email' => 'some@email.com',
			'full_name' => 'John Smith some@email.com',
		], $data, 'Data should be equal to the model attributes with mutated attributes');
	}

	/**
	 * @depends testGetModelDataWithMutatedFields
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::getData
	 * @return void
	 */
	public function testGetModelDataWithConfigMapping(): void
	{
		Config::shouldReceive('get')
			->with('elastic_sync.indexes.' . get_class($this->model)::elastic()->index(), [])
			->andReturn([
				get_class($this->model) => [
					'id',
					'name' => 'full_name',
					'post.name',
				],
			]);

		$data = ElasticObserver::getData($this->model);
		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => 1,
			'name' => 'John Smith some@email.com',
			'post.name' => 'Some post',
		], $data, 'Data should be equal to config attributes from the model and extra fields');
	}

	/**
	 * @depends testGetModelDataWithConfigMapping
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::getData
	 * @return void
	 */
	public function testGetModelDataWithExtraFields(): void
	{
		get_class($this->model)::elastic()
			->addExtra('group', function (Model $model): string {
				return $model->getTable();
			});

		$data = ElasticObserver::getData($this->model);
		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => 1,
			'name' => 'John Smith some@email.com',
			'post.name' => 'Some post',
			'group' => 'users',
		], $data, 'Data should be equal to config attributes from the model and extra fields');
	}

	/**
	 * @depends testGetModelDataWithExtraFields
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::getData
	 * @return void
	 */
	public function testGetModelDataWithModifieredFieldValues(): void
	{
		get_class($this->model)::elastic()
			->addCallback('name', function (string $value): string {
				return explode(' ', $value)[0];
			});

		get_class($this->model)::elastic()
			->addCallback('name', function (string $value, array $data): string {
				return $value . ' ' . $data['group'];
			});

		get_class($this->model)::elastic()
			->addCallback('name', function (string $value, array $data, Model $model): string {
				return $value . ' ' . $model->email . ' ' . $data['post.name'];
			});

		get_class($this->model)::elastic()
			->addCallback('group', function (string $value, array $data): string {
				return $value . ' ' . $data['name'];
			});

		$data = ElasticObserver::getData($this->model);
		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => 1,
			'name' => 'John users some@email.com Some post',
			'post.name' => 'Some post',
			'group' => 'users John users some@email.com Some post',
		], $data, 'Data should be equal to config attributes from the model with modifiers');
	}

	/**
	 * @depends testGetModelDataWithModifieredFieldValues
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::getData
	 * @return void
	 */
	public function testGetModelDataWithCustomMappingAndRelations(): void
	{
		get_class($this->model)::elastic()
			->setMapping(function (): array {
				return [
					'id',
					'name',
					'posts',
					'post.name',
					'group',
				];
			});

		get_class($this->model)::elastic()
			->addExtra('relations', function (): array {
				return [[
					'some' => 'value',
				]];
			});

		$data = ElasticObserver::getData($this->model);
		$this->assertNotEmpty($data, 'Data must not be empty');
		$this->assertEquals([
			'id' => 1,
			'name' => 'John users some@email.com Some post',
			'post.name' => 'Some post',
			'group' => 'users John users some@email.com Some post',
			'posts' => null,
			'relations' => [
				['some' => 'value'],
			],
		], $data, 'Data should be equal to config attributes from the model with modifiers');
	}
}
