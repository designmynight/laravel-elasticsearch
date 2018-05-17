<?php

return [
    'connections' => [
        'elasticsearch' => [
            'driver'   => 'elasticsearch',
            'host'     => env('ELASTICSEARCH_HOST', 'localhost'),
            'port'     => env('ELASTICSEARCH_PORT', 9200),
            'database' => env('ELASTICSEARCH_DATABASE'),
            'username' => env('ELASTICSEARCH_USERNAME'),
            'password' => env('ELASTICSEARCH_PASSWORD'),
            'suffix'   => env('ELASTICSEARCH_INDEX_SUFFIX'),
        ]
    ]
];
