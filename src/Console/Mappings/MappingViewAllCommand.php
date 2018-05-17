<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\HasHost;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

/**
 * Class MappingViewAllCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingViewAllCommand extends Command
{

    use HasHost;

    /** @var Client $client */
    protected $client;

    /**
     * MappingViewAllCommand constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        parent::__construct();

        $this->client = $client;
        $this->host = $this->getHost();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->info($this->getIndexes());
    }

    /**
     * @return string
     */
    protected function getIndexes():string
    {
        return $this->client->get("{$this->host}/_cat/indices?v")->getBody();
    }
}