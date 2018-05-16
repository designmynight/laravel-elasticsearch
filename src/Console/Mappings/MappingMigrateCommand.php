<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\HasHost;
use DesignMyNight\Elasticsearch\Console\Mappings\Traits\UpdatesAlias;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class MappingMigrateCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingMigrateCommand extends Command
{

    use HasHost;
    use UpdatesAlias;

    /** @var Client $client */
    protected $client;

    /** @var Builder $connection */
    protected $connection;

    /** @var string $description */
    protected $description = 'Index new mapping';

    /** @var Filesystem $files */
    protected $files;

    /** @var string $signature */
    protected $signature = 'migrate:mappings {artisan_command : Local Artisan indexing command.} {--S|swap : Automatically update alias.}';

    /**
     * MappingMigrateCommand constructor.
     *
     * @param Client $client
     */
    public function __construct(Client $client, Filesystem $files)
    {
        parent::__construct();

        $this->client = $client;
        $this->connection = $this->getConnection();
        $this->files = $files;
        $this->host = $this->getHost();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $mappings = $this->getMappingFiles();
        $mappingMigrations = $this->connection->orderBy('mapping')->orderBy('batch')->pluck('mapping');
        $pendingMappings = $this->pendingMappings($mappings, $mappingMigrations->toArray());

        $this->runPending($pendingMappings);
    }

    /**
     * @return Builder
     */
    protected function getConnection():Builder
    {
        return DB::connection()->table('mappings');
    }

    /**
     * @return SplFileInfo[]
     */
    protected function getMappingFiles():array
    {
        return $this->files->files(base_path('database/mappings'));
    }

    /**
     * @param string $mapping
     * @param bool   $withSuffix
     *
     * @return string
     */
    protected function getMappingName(string $mapping, bool $withSuffix = false):string
    {
        $mapping = str_replace('.json', '', $mapping);

        if ($withSuffix) {
            $mapping .= config('database.connections.elasticsearch.suffix');
        }

        return $mapping;
    }

    /**
     * @param array $files
     * @param array $migrations
     *
     * @return SplFileInfo[]
     */
    protected function pendingMappings(array $files, array $migrations):array
    {
        return Collection::make($files)
            ->reject(function (SplFileInfo $file) use ($migrations):bool {
                return in_array($this->getMappingName($file->getFilename()), $migrations);
            })->values()->toArray();
    }

    /**
     * @param SplFileInfo $mapping
     */
    protected function putMapping(SplFileInfo $mapping):void
    {
        $index = $this->getMappingName($mapping->getFileName(), true);

        $this->client->put("{$this->host}/{$index}", [
            'headers' => [
                'Content-Type' => 'application/json'
            ],
            'body'    => $mapping->getContents()
        ]);
    }

    /**
     * @param SplFileInfo[] $pending
     */
    protected function runPending(array $pending):void
    {
        if (empty($pending)) {
            $this->info('No new mappings to migrate.');

            return;
        }

        $batch = $this->connection->max('batch') + 1;

        foreach ($pending as $mapping) {
            $index = $this->getMappingName($mapping->getFileName());

            $this->info("Migrating mapping: {$index}");

            $this->connection->insert([
                'batch'   => $batch,
                'mapping' => $this->getMappingName($index),
            ]);

            $this->info("Migrated mapping: {$index}");
            $this->info("Indexing mapping: {$index}");

            try {
                // Create index.
                $this->putMapping($mapping);
            }
            catch (\Exception $exception) {
                $this->error("Failed to put mapping: {$index}");

                return;
            }

            // Begin indexing.
            $this->call($this->argument('artisan_command'), [
                'index' => $index
            ]);

            $this->info("Indexed mapping: {$index}");

            if ($this->option('swap')) {
                $this->updateAlias($this->getMappingName($index, true));
            }
        }
    }
}
