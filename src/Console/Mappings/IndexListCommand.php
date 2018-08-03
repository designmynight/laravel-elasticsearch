<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\GetsIndices;
use Elasticsearch\ClientBuilder;
use Illuminate\Support\Collection;

/**
 * Class IndexListCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexListCommand extends Command
{

    use GetsIndices;

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
        parent::__construct($client);
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
     * @param string $alias
     *
     * @return array
     */
    protected function getIndicesForAlias(string $alias = '*'):array
    {
        try {
            $aliases = collect($this->client->cat()->aliases());

            return $aliases
                ->sortBy('alias')
                ->when($alias !== '*', function (Collection $aliases) use ($alias) {
                    return $aliases->filter(function ($item) use ($alias) {
                        return str_contains($item['alias'], $alias);
                    });
                })
                ->values()
                ->toArray();
        }
        catch (\Exception $exception) {
            $this->error("Failed to retrieve alias {$alias}");
        }

        return [];
    }
}
