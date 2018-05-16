<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

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
    protected $signature = 'index:swap {alias : Name of alias to be updated.} {index : Name of index to be updated to.} {old_index? : Name of current index.}';

    /**
     * MappingMigrateCommand constructor.
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

        if ($oldIndex = $this->argument('old_index')) {
            $this->updateAlias($index, $alias, $oldIndex);

            return;
        }

        $this->updateAlias($index, $alias);
    }
}