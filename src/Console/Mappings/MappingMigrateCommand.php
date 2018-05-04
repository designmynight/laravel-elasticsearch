<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Mapping;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;

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

    /** @var string $signature */
    protected $signature = 'migrate:mapping {--S|swap : Automatically update alias.}';

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
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        /**
         * TODO: Check existence of new mapping file (from "mappings" collection).
         * TODO: Begin index of collection(s) (Which collections?) if new mapping file is found.
         * TODO: Swap mapping alias.
         */
        $mappings = $this->getMappingFiles();
        $mappingMigrations = Mapping::orderBy('mapping')->orderBy('batch')->pluck('mapping');

        $pendingMappings = $this->pendingMappings($mappings, $mappingMigrations);

        $this->runPending($pendingMappings);
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
            $this->info("Migrating: {$mapping}");

            Mapping::create([
                'batch'   => $batch,
                'mapping' => $mapping,
            ]);

            // Begin indexing.

            $this->info("Migrated: {$mapping}");
        }
    }
}
