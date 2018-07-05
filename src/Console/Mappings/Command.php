<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Connection;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command as BaseCommand;

/**
 * Class Command
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
abstract class Command extends BaseCommand
{

    /** @var ClientBuilder $client */
    public $client;

    /**
     * Command constructor.
     *
     * @param ClientBuilder $client
     */
    public function __construct(ClientBuilder $client)
    {
        parent::__construct();

        $this->client = new Connection(config('database.connections.elasticsearch'));
    }
}
