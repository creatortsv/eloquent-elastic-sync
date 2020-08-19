<?php

namespace Creatortsv\EloquentElasticSync\Test;

use PHPUnit\Framework\TestCase as FrameworkTestCase;
use Creatortsv\EloquentElasticSync\ElasticObservant;
use Illuminate\Database\Eloquent\Model;
use Faker\Factory;
use Illuminate\Support\Facades\Config;

class TestCase extends FrameworkTestCase
{
	/**
	 * @var Model
	 */
	protected $model;

	/**
	 * @var string
	 */
	protected $class;

	/**
	 * @return void
	 */
	public function setUp(): void
	{
		parent::setUp();

		Config::shouldReceive('get')
			->once()
			->with('elastic_sync.disabled', false)
			->andReturn(false);

		$faker = Factory::create();
		$this->model = $this->createModel('flights', [
			'id' => $faker->numberBetween(1, 10),
			'name' => $faker->company,
		]);

		$this
			->model
			->setRelation('destination', $this->createModel('destinations', [
				'id' => $faker->numberBetween(1, 10),
				'name' => $faker->country,
			]));

		$this->class = get_class($this->model);
	}

	/**
	 * @return void
	 */
	public function tearDown(): void
	{
		parent::tearDown();

		get_class($this->model)::elastic(true);

		$this->model = null;
		$this->class = null;
	}

	/**
	 * @param string $table
	 * @param array $attributes
	 * @return Model
	 */
	protected function createModel(string $table, array $attributes = []): Model
	{
		$model = new class extends Model
		{
			use ElasticObservant;

			public function getFullNameAttribute(): string
			{
				return $this->getTable() . '.' . $this->name;
			}
		};

		$model->setTable($table);
		$model->fillable(array_keys($attributes));
		$model->fill($attributes);

		return $model;
	}
}
