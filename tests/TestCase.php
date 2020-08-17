<?php

namespace Creatortsv\EloquentElasticSync\Test;

use PHPUnit\Framework\TestCase as FrameworkTestCase;
use Creatortsv\EloquentElasticSync\ElasticIndexConfig;
use Creatortsv\EloquentElasticSync\ElasticObservant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Config;

class TestCase extends FrameworkTestCase
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
		parent::setUp();

		Config::shouldReceive('get')
			->with('elastic_sync.connection', 'default')
			->andReturn('default');

		Config::shouldReceive('get')
			->with('elastic_sync.connections.default.host')
			->andReturn('localhost');

		Config::shouldReceive('get')
			->with('elastic_sync.connections.default.port')
			->andReturn('9200');

		$this->model = new class ([
			'id' => 1,
			'name' => 'John Smith',
			'email' => 'some@email.com',
		]) extends Model
		{
			use ElasticObservant;

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

		$this->model->setRelation('post', new class ([
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
		});

		$this->config = new ElasticIndexConfig(get_class($this->model));
	}

	/**
	 * @return void
	 */
	public function tearDown(): void
	{
		parent::tearDown();

		$this->model = null;
		$this->config = null;
	}
}
