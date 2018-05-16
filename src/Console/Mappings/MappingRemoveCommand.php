<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Exceptions\FailedToDeleteIndex;
use DesignMyNight\Elasticsearch\Console\Mappings\Traits\HasHost;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

/**
 * Class MappingRemoveCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingRemoveCommand extends Command
{

    use HasHost;

    /** @var Client $client */
    protected $client;

    /** @var string $description */
    protected $description = 'Remove index from Elasticsearch';

    /** @var string $signature */
    protected $signature = 'index:remove {index : Name of the index to remove.}';

    /**
     * MappingRemoveCommand constructor.
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
        $index = $this->argument('index');

        if (app()->environment('production') && !$this->confirm("Are you sure you wish to remove the index {$index}?")) {
            return;
        }

        $this->removeIndex($index);
    }

    /**
     * @param string $index
     */
    protected function removeIndex(string $index):void
    {
        $this->info("Removing index: {$index}");

        try {
            $body = $this->client->delete("{$this->host}/{$index}")->getBody();
            $body = json_decode($body);

            if (isset($body['error'])) {
                throw new FailedToDeleteIndex($body['error']['reason'], $body['status']);
            }
        }
        catch (\Exception $exception) {
            $this->error("Failed to remove index: {$index}\n\n{$exception->getMessage()}");

            return;
        }

        $this->info("Removed index: {$index}");
    }
}