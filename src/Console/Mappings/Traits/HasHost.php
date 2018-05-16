<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings\Traits;

/**
 * Trait HasHost
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings\Traits
 */
trait HasHost
{

    /** @var string $host */
    protected $host;

    /**
     * @return string
     */
    protected function getHost():string
    {
        $elasticsearchConfig = config('database.connections.elasticsearch');

        return "{$elasticsearchConfig['host']}:{$elasticsearchConfig['port']}";
    }
}