<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Class IndexListCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexListCommand extends Command
{

    /** @var ClientBuilder $client */
    public $client;

    /** @var string $description */
    protected $description = 'View all Elasticsearch indices';

    /** @var string $signature */
    protected $signature = 'index:list {--A|alias= : Name of alias indexes belong to.}';

    /**
     * IndexListCommand constructor.
     *
     * @param ClientBuilder $client
     */
    public function __construct(ClientBuilder $client)
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
        if ($alias = $this->option('alias')) {
            $indices = $this->getIndicesForAlias($alias);

            if (empty($indices)) {
                $this->line('No aliases found.');

                return;
            }

            $this->table(array_keys($indices[0]), $indices);

            return;
        }

        if ($indices = $this->getIndices()) {
            if (empty($indices)) {
                $this->line('No indexes were found.');

                return;
            }

            $this->table(array_keys($indices[0]), $indices);
        }
    }

    /**
     * @return array
     */
    protected function getIndices():array
    {
        try {
            return collect($this->client->build()->cat()->indices())->sortBy('index')->toArray();
        }
        catch (\Exception $exception) {
            $this->error('Failed to retrieve indices.');
        }

        return [];
    }

    /**
     * @param string $alias
     *
     * @return array
     */
    protected function getIndicesForAlias(string $alias = '*'):array
    {
        try {
            $aliases = collect($this->client->build()->cat()->aliases());

            return $aliases
                ->sortBy('alias')
                ->when($alias !== '*', function (Collection $aliases) use ($alias) {
                    return $aliases->filter(function ($item) use ($alias) {
                        return str_contains($item['alias'], $alias);
                    });
                })
                ->toArray();
        }
        catch (\Exception $exception) {
            $this->error("Failed to retrieve alias {$alias}");
        }

        return [];
    }
}
