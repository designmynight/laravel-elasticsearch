<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Exceptions\FailedToDeleteIndex;
use DesignMyNight\Elasticsearch\Console\Mappings\Traits\UpdatesAlias;
use GuzzleHttp\Client;
use Illuminate\Console\Command;

/**
 * Class MappingSwapAliasCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingSwapAliasCommand extends Command
{

    use UpdatesAlias;

    /** @var Client $client */
    protected $client;

    /** @var string $description */
    protected $description = 'Swap Elasticsearch alias';

    /** @var string $host */
    protected $host;

    /** @var string $signature */
    protected $signature = 'index:swap {alias : Name of alias to be updated.} {index : Name of index to be updated to.} {old_index? : Name of current index.} {--R|remove_old_index : Deletes the old index.}';

    /**
     * MappingSwapAliasCommand constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        parent::__construct();

        $this->client = $client;
        $this->host = config('database.elasticsearch.host');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        ['alias' => $alias, 'index' => $index] = $this->arguments();

        $arguments = [$index, $alias];

        if ($oldIndex = $this->argument('old_index')) {
            $arguments[] = $oldIndex;
        }

        $this->updateAlias(...$arguments);

        if ($this->option('remove_old_index')) {
            $this->removeIndex($oldIndex);
        }
    }

    /**
     * @param string $index
     */
    protected function removeIndex(string $index):void
    {
        if (app()->environment('production') && !$this->confirm("Are you sure you wish to delete the index {$index}?")) {
            return;
        }

        $this->info("Deleting index: {$index}");

        try {
            $body = $this->client->delete("{$this->host}/$index")->getBody();
            $body = json_decode($body);

            if (isset($body['error'])) {
                throw new FailedToDeleteIndex($body['error']['reason'], $body['status']);
            }
        }
        catch (\Exception $exception) {
            $this->error("Failed to delete index: {$index}\n\n{$exception->getMessage()}");

            return;
        }

        $this->info("Successfully deleted index: {$index}");
    }
}