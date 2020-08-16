<?php

namespace Creatortsv\EloquentElasticSync\Test;

use Creatortsv\EloquentElasticSync\ElasticIndexConfig;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

class ElasticIndexConfigTest extends TestCase
{


    /**
     * Testing method setIndexName
     */
    public function testSetIndexName(): void
    {
        $class = new class extends Model
        {
            protected $table = 'users';
        };

        $elastic = new ElasticIndexConfig(get_class($class));
        $elastic->setIndexName('test');
        $this->assertEquals('test', $elastic->index());
    }
}
