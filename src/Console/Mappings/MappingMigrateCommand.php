<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\UpdatesAlias;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Class MappingMigrateCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingMigrateCommand extends Command
{

    use UpdatesAlias;

    /** @var Client $client */
    protected $client;

    /** @var Builder $connection */
    protected $connection;

    /** @var string $description */
    protected $description = 'Index new mapping';

    /** @var Filesystem $files */
    protected $files;

    /** @var string $host */
    protected $host;

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
        $mappingMigrations = $this->connection->orderBy('mapping')->orderBy('batch')->pluck('mapping');
        $pendingMappings = $this->pendingMappings($mappings, $mappingMigrations->toArray());

        $this->runPending($pendingMappings);
    }

    /**
     * @return array
     */
    protected function getMappingFiles():array
    {
        return $this->files->files(base_path('database/mappings'));
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
    protected function runPending(array $pending):void
    {
        if (empty($pending)) {
            $this->info('No new mappings to migrate.');

            return;
        }

        $batch = $this->connection->max('batch') + 1;

        foreach ($pending as $mapping) {
            $this->info("Migrating mapping: {$mapping}");

            $this->connection->insert([
                'batch'   => $batch,
                'mapping' => $mapping,
            ]);

            $this->info("Migrated mapping: {$mapping}");
            $this->info("Indexing mapping: {$mapping}");

            // Begin indexing.
            Artisan::call($this->argument('artisan_command'));

            $this->info("Indexed mapping: {$mapping}");

            if ($this->option('swap')) {
                $this->updateAlias($mapping);
            }
        }
    }

    /**
     * @return Builder
     */
    protected function getConnection():Builder
    {
        return DB::connection('default')->table('mappings');
    }
}
