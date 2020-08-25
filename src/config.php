<?php

return [

    'disabled' => false,

    /*
             * Which connection settings will be used
             */
    'connection' => 'default',

    /*
     * Describe different connections
     */
    'connections' => [

        /*
         * Connection name
         */
        'default' => [

            'host' => 'localhost',
            'port' => 9200,

        ],

    ],

    /**
     * The field of the mapping which will be used as the id of an elastic index document
     */
    'index_id_field' => 'id',

    /**
     * If it is true, then attributes with mutated of an eloquent model will be used
     */
    'use_mutated_fields' => false,

    /*
     * Which index will be used
     */
    'indexes' => [

        /*
         * Describe different indexes & its fields
         */
        'default' => null,

        /*
         * Describe different indexes & its fields
         * fields formart: [elastic property] => [alias]? optional
        'some-index' => [

            'base_mapping' => [

                'id',
                'name',

            ],

            'App\\User' => [

                'id',
                'name' => 'email',
                'phone' => 'phones.number',
                'avatar' => 'avatar.image.safe_url',

            ],

        ], */

    ],

    /**
     * Event classes
    'events' => [

        'saved' => Event::class,
        'deleted' => Event::class,
        'failed' => Event::class,

    ], */

    'bulk_sync' => [

        'chunk_size' => 10,

    ],

];
