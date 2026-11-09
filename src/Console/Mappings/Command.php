<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Connection;
use OpenSearch\Client;
use Illuminate\Console\Command as BaseCommand;

/**
 * Class Command
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
abstract class Command extends BaseCommand
{

    /** @var Connection|Client $client */
    public $client;

    /**
     * Command constructor.
     */
    public function __construct()
    {
        parent::__construct();

        if ($config = config('database.connections.elasticsearch')) {
            $this->client = new Connection($config);
        }
    }

    /**
     * @return void
     */
    abstract public function handle();
}
