<?php

return [
    'connections' => [
        'elasticsearch' => [
            'driver'   => 'elasticsearch',
            'host'     => env('ELASTICSEARCH_HOST', 'localhost'),
            'port'     => env('ELASTICSEARCH_PORT', 9200),
            'database' => env('ELASTICSEARCH_DATABASE', 'your_es_index'),
            // 'username' => env('ELASTICSEARCH_USERNAME', 'optional_es_username'),
            // 'password' => env('ELASTICSEARCH_PASSWORD', 'optional_es_username'),
            // 'suffix'   => env('ELASTICSEARCH_INDEX_SUFFIX', 'optional_es_index_suffix'),
        ]
    ]
];
