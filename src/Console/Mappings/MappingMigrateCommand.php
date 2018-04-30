<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

/**
 * Class MappingMigrateCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingMigrateCommand extends Command
{
    /** @var string $signature */
    protected $signature = 'migrate:mapping';

    /** @var string $description */
    protected $description = 'Index new mapping.';

    /** @var Client $client */
    protected $client;

    /**
     * MappingMigrateCommand constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        parent::__construct();

        $this->client = $client;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        /**
         * TODO: Check existence of new mapping file (from "mappings" collection).
         * TODO: Begin index of collection(s) (Which collections?) if new mapping file is found.
         * TODO: Swap mapping alias.
         */
    }
}
