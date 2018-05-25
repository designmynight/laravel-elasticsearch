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
     * @param string $host
     */
    public function setHost(string $host):void
    {
        $this->host = $host;
    }

    /**
     * @return string
     */
    protected function getHost():string
    {
        ['host' => $host, 'port' => $port] = config('database.connections.elasticsearch');

        return "{$host}:{$port}";
    }
}