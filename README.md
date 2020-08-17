# Eloquent Elasticsearch Sync
This package helps you to sync your eloquent models with elasticsearch documents

![tests](https://github.com/creatortsv/eloquent-elastic-sync/workflows/tests/badge.svg?branch=master) 
[![License](https://poser.pugx.org/creatortsv/eloquent-elastic-sync/license)](//packagist.org/packages/creatortsv/eloquent-elastic-sync) 
[![Latest Stable Version](https://poser.pugx.org/creatortsv/eloquent-elastic-sync/v)](//packagist.org/packages/creatortsv/eloquent-elastic-sync) 

# Prerequisites
- ```PHP >= 7.2```

# Installation
1. Run composer command:
```bash
composer require creatortsv/eloquent-elastic-sync
```

2. Add ```EloquentObservant``` trait to your eloquent models which will be syncronized with **elasticsearch**:
```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Creatortsv\EloquentElasticSync\EloquentObservant;

class Flight extends Model
{
    /* ... */
    use EloquentObservant;
    /* ... */
}
```

# Configuration
You may want to create ```config/elastic_sync.php``` config file or import it from the package using this artisan command
```bash
php artisan vendor:publish --provider="Creatortsv\EloquentElasticSync\EloquentElasticSyncProvider"
```

For the minimum configuration You should edit ```host``` and ```port``` properties of the ```connections.default``` section of your ```config/elastic_sync.php``` config file:
```php
<?php

return [

    /*
     * Describe different connections
     */
    'connections' => [

        /*
         * Connection name
         */
        'default' => [

            'host' => env('ELASTIC_HOST'),
            'port' => env('ELASTIC_PORT'),

        ],

    ],

];

```

If You want to use another connection by default then describe your own connection into the ```connections``` section and change the ```connection``` property of the ```config/elastyc_sync.php``` file:
```php
<?php

return [
    
    /*
     * Which connection settings will be used
     */
    'connection' => 'awesome_connection', // default

    /*
     * Describe different connections
     */
    'connections' => [

        /* ... */
        
        'awesome_connection' => [

            'host' => 'awesome.com',
            'port' => 9201,

        ],
        
        /* ... */

    ],
    
];

```

If You want to use different connections for your eloquent models:

- Describe your own connections for some eloquent models into the ```connections``` section of the ```config/elastyc_sync.php``` file
```php
<?php

return [
    
    /*
     * Describe different connections
     */
    'connections' => [

        /* ... */
        
        'flights-connection' => [

            'host' => 'awesome.users.com',
            'port' => 9202,

        ],
        
        /* ... */

    ],

];

```
- Override the ```php protected static function booted(): void``` method into your Eloquent models and set connection name like this
```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Creatortsv\EloquentElasticSync\EloquentObservant;

class Flight extends Model
{
    use EloquentObservant;
    
    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        static::elastic()->setConnection('flights-connection');
    }
    
    /* ... */
}
```

# Data mapping
By default an Eloquent model will be syncronized to the Elasticsearch document with its own attributes (without relations).
But if You want to sync models with mutated attributus, for example:
```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Creatortsv\EloquentElasticSync\EloquentObservant;

class Flight extends Model
{
    use EloquentObservant;
    
    /* ... */
    
    /**
     * Get full name of the flight
     *  @return string
     */
    public function getFlightNameAttribute(): string
    {
        return $this->name . ' ' . $this->destination;
    }
    
    /* ... */
}
```
Then You should change the ```use_mutated_fields``` property of your ```config/elastic_sync.php``` file to ```true```
```php
<?php

return [
    /* ... */
    
    'use_mutated_fields' => true,
    
    /* ... */
];
```

You can use your own data mapping and index name for your Eloquent models, this data will be used for interacting with Elasticserch index|update|delete actions.
By default Index name for Eloquent models (if ```indexes.default``` property is ```null```) is equal to the table name property of the Model.
You can do it with two different ways:

1. Describe a data mapping into ```indexes``` section of the ```config/elastyc_sync.php``` file and set the ```default``` option.
Also Data mapping may be described for every single Model
```php
<?php

return [
    /* ... */
    
    /*
     * Describe different indexes & its fields
     */
    'indexes' => [
    
        'default' => 'my-index', // if it is null then attributes of model will be used
        
        /**
         * Index name it'll bwe used as index for the documents
         */
        'my-index' => [
        
            /**
             * Base mapping for models
             */
            'base_mapping' => [
            
                'id',
                'name,
            /** 'avatar' => 'avatar.safe_url', Dot anotation allowed for the relations */
            
            ],
            
            App\Flight::class => [
            
                /* ... This mapping will be merged with the base_mapping of the described index */
            
            ],
        
        ],
    
    ],
    
    /* ... */
];
```
2. Configure it behavior with ```booted()``` method on your Eloquent models
```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Creatortsv\EloquentElasticSync\EloquentObservant;

class Flight extends Model
{
    use EloquentObservant;
    
    /* ... */
    
    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        /**
         * Use custom data mapping
         * Don't use real data, only field names
         */
        static::elastic()->setMapping(function (Model $model): array {
            return [
                /* Describe yor mapping here */
            ];
        });
        
        /**
         * This name will be used as index for the Elasticsearch document
         */
        static::elastic()->setIndexName('flights-index');
    }
    
    /* ... */
}
```

For each Eloquent model class You can add Extra fields for the indexing document, like this
```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Creatortsv\EloquentElasticSync\EloquentObservant;

class Flight extends Model
{
    use EloquentObservant;
    
    /* ... */
    
    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        /**
         * Using callback
         */
        static::elastic()->addExtra('group', function (Model $model): array {
            return $model->getTable();
        });
        
        /**
         * Using simple assign action
         */
        static::elastic()->addExtra('my_field', 'my_value');
    }
    
    /* ... */
}
```

Also You can modify fields and extra fields before send it to the Elasticsearch document with callback functions, like this
```php
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Creatortsv\EloquentElasticSync\EloquentObservant;

class Flight extends Model
{
    use EloquentObservant;
    
    /* ... */
    
    /**
     * Perform any actions required after the model boots.
     *
     * @return void
     */
    protected static function booted(): void
    {
        /**
         * Modify group extra field for example
         * @param mixed $group It is value after data mapping operation
         * @param array $data All data values after data mapping operation
         */
        static::elastic()->addCallback('group', function ($group, array $data, Model $model) {
            return $group . ' company: ' . $model->company->name; // result: flights company: Some Company Name
        });
        
        /**
         * Another time
         */
        static::elastic()->addCallback('group', function ($group, array $data, Model $model) {
            return $group . ' fixed'; // result: flights company: Some Company Name fixed
        });
    }
    
    /* ... */
}
```

# Artisan Command
Work in progress ...
