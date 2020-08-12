<?php

return [

    'connection' => 'default',

    'connections' => [

        'default' => [

            'host' => env('ELASTIC_HOST'),
            'port' => env('ELASTIC_PORT'),

        ],

    ],

];
