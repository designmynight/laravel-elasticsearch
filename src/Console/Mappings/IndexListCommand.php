<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Elasticsearch\ClientBuilder;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Class IndexListCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexListCommand extends Command
{

    /** @var string $description */
    protected $description = 'View all Elasticsearch indices';

    /** @var string $signature */
    protected $signature = 'index:list {--A|alias= : Name of alias indexes belong to.}';

    /** @var ClientBuilder $client */
    private $client;

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
            foreach ($this->getIndicesForAlias($alias) as $key => $indices) {
                $this->info("Alias: $key");

                foreach ($indices as $index => $item) {
                    $this->line("[$index]: $item");
                }

                $this->line('');
            }

            return;
        }

        if ($indices = $this->getIndices()) {
            $this->table(array_keys($indices[0]), $indices);
        }
    }

    /**
     * @return null|array
     */
    protected function getIndices():?array
    {
        try {
            return collect($this->client->create()->build()->cat()->indices())->sortBy('index')->toArray();
        }
        catch (\Exception $exception) {
            $this->error('Failed to retrieve indices.');
        }

        return null;
    }

    /**
     * @param string $alias
     *
     * @return array
     */
    protected function getIndicesForAlias(string $alias = '*'):array
    {
        try {
            $aliases = collect($this->client->create()->build()->cat()->aliases());

            return $aliases
                ->groupBy(function (array $item):string {
                    return $item['alias'];
                })
                ->when($alias !== '*', function (Collection $aliases) use ($alias) {
                    return $aliases->filter(function ($item, $key) use ($alias) {
                        return $key === $alias;
                    });
                })
                ->map(function (Collection $indices):Collection {
                    return $indices->sortBy('index')->pluck('index');
                })
                ->toArray();
        }
        catch (\Exception $exception) {
            $this->error("Failed to retrieve alias {$alias}");
        }

        return [];
    }
}