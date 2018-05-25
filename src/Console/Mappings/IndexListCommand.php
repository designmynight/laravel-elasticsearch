<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\HasHost;
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

    use HasHost;

    /** @var Client $client */
    public $client;

    /** @var string $description */
    protected $description = 'View all Elasticsearch indices';

    /** @var string $signature */
    protected $signature = 'index:list {--A|alias= : Name of alias indexes belong to.}';

    /**
     * IndexListCommand constructor.
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

    /**
     * @param string $alias
     *
     * @return array
     */
    protected function getIndicesForAlias(string $alias = '*'):array
    {
        try {
            $body = $this->client->get("{$this->host}/_alias/{$alias}")->getBody();
            $body = collect(json_decode($body, true));

            return $body->groupBy(function ($item):string {
                return key($item['aliases']);
            }, true)->map(function (Collection $indices):Collection {
                return $indices->sortKeys()->keys();
            })->toArray();
        }
        catch (\Exception $exception) {
            $this->error("Failed to retrieve alias {$alias}");
        }

        return [];
    }
}