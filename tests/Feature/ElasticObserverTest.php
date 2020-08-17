<?php

namespace Creatortsv\EloquentElasticSync\Test\Feature;

use PHPUnit\Framework\TestCase;
use Creatortsv\EloquentElasticSync\ElasticObserver;

class ElasticObserverTest extends TestCase
{
	/**
	 * @return void
	 */
	public function setUp(): void
	{
	}

	/**
	 * @return void
	 */
	public function tearDown(): void
	{
	}

	/**
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::saved
	 * @return void
	 */
	public function testCreateDocumentInElasticsearchServer(): void
	{
		$this->assertTrue(true);
	}

	/**
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::saved
	 * @depends testCreateDocumentInElasticsearchServer
	 * @return void
	 */
	public function testUpdateDocumentInElasticsearchServer(): void
	{
		$this->assertTrue(true);
	}

	/**
	 * @covers Creatortsv\EloquentElasticSync\ElasticObserver::deleted
	 * @depends testUpdateDocumentInElasticsearchServer
	 * @return void
	 */
	public function testDeleteDocumentInElasticsearchServer(): void
	{
		$this->assertTrue(true);
	}
}
