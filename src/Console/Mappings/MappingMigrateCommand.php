<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Mapping;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;

/**
 * Class MappingMigrateCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingMigrateCommand extends Command
{
    /** @var Client $client */
    protected $client;

    /** @var string $description */
    protected $description = 'Index new mapping.';

    /** @var Filesystem $files */
    protected $files;

    /** @var string $host */
    protected $host;

    /** @var string $signature */
    protected $signature = 'migrate:mapping {command : Local Artisan indexing command.} {--S|swap : Automatically update alias.}';

    /**
     * MappingMigrateCommand constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client, Filesystem $files)
    {
        parent::__construct();

        $this->client = $client;
        $this->files = $files;
        $this->host = config('database.elasticsearch.host');
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $mappings = $this->getMappingFiles();
        $mappingMigrations = Mapping::orderBy('mapping')->orderBy('batch')->pluck('mapping');

        $pendingMappings = $this->pendingMappings($mappings, $mappingMigrations);

        $this->runPending($pendingMappings);
    }

    /**
     * @param string $alias
     *
     * @return string
     */
    protected function getActiveIndex(string $alias):string
    {
        try {
            $body = $this->client->get("{$this->host}/{$alias}/_alias/*")->getBody();

            return array_keys(json_decode($body))[0];
        }
        catch (\Exception $exception) {
            $this->error("Failed to retrieve the current active index.");
        }

        return '';
    }

    /**
     * @param string $mapping
     *
     * @return string
     */
    protected function getAlias(string $mapping):string
    {
        return preg_replace('/[0-9_]+/', '', $mapping, 1);
    }

    /**
     * @return array
     */
    protected function getMappingFiles():array
    {
        return $this->files->files(base_path('/database/mappings'));
    }

    /**
     * @param string $mapping
     *
     * @return string
     */
    protected function getMappingName(string $mapping):string
    {
        return str_replace('.php', '', $mapping);
    }

    /**
     * @param array $files
     * @param array $migrations
     *
     * @return array
     */
    protected function pendingMappings(array $files, array $migrations):array
    {
        return Collection::make($files)
            ->reject(function (string $file) use ($migrations):bool {
                return in_array($this->getMappingName($file), $migrations);
            })->values()->toArray();
    }

    /**
     * @param array $pending
     */
    protected function runPending(array $pending)
    {
        if (empty($pending)) {
            $this->info('No new mappings to migrate.');

            return;
        }

        $batch = Mapping::max('batch') + 1;

        foreach ($pending as $mapping) {
            $this->info("Migrating mapping: {$mapping}");

            Mapping::create([
                'batch'   => $batch,
                'mapping' => $mapping,
            ]);

            $this->info("Migrated mapping: {$mapping}");
            $this->info("Indexing mapping: {$mapping}");

            // Begin indexing.
            Artisan::call($this->argument('command'));

            $this->info("Indexed mapping: {$mapping}");

            if ($this->option('swap')) {
                $this->updateAliases($mapping);
            }
        }
    }

    /**
     * @param string $mapping
     *
     * @return bool
     */
    protected function updateAliases(string $mapping):bool
    {
        $this->info("Updating mapping alias: {$mapping}");

        $alias = $this->getAlias($mapping);
        $body = [
            'actions' => [
                [
                    'remove' => [
                        'index' => $this->getActiveIndex($alias),
                        'alias' => $alias
                    ],
                    'add'    => [
                        'index' => $mapping,
                        'alias' => $alias
                    ]
                ]
            ]
        ];

        try {
            $this->client->post("{$this->host}/_aliases", [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode($body)
            ]);
        }
        catch (\Exception $exception) {
            $this->error("Failed to update alias: {$mapping}");

            return false;
        }

        $this->info("Updated mapping alias: {$mapping}");

        return true;
    }
}
