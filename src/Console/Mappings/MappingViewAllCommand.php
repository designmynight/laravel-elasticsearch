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

    /** @var string $description */
    protected $description = 'View all Elasticsearch indices';

    /** @var string $signature */
    protected $signature = 'index:list';

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
        if ($indices = $this->getIndices()) {
            $this->line($indices);
        }
    }

    /**
     * @return null|string
     */
    protected function getIndices():?string
    {
        try {
            return $this->client->get("{$this->host}/_cat/indices?v")->getBody();
        }
        catch (\Exception $exception) {
            $this->error('Failed to retrieve indices.');
        }

        return null;
    }
}