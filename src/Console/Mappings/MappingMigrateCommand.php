<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Exceptions\FailedToPutNewMapping;
use DesignMyNight\Elasticsearch\Console\Mappings\Traits\HasConnection;
use DesignMyNight\Elasticsearch\Console\Mappings\Traits\UpdatesAlias;
use Elasticsearch\ClientBuilder;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Class MappingMigrateCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingMigrateCommand extends Command
{

    use HasConnection;
    use UpdatesAlias;

    /** @var ClientBuilder $client */
    public $client;

    /** @var Filesystem $files */
    public $files;

    /** @var string $description */
    protected $description = 'Index new mapping';

    /** @var string $signature */
    protected $signature = 'migrate:mappings {artisan-command? : Local Artisan indexing command. Defaults to config.} {--S|swap : Automatically update alias.}';

    /**
     * MappingMigrateCommand constructor.
     *
     * @param Client $client
     */
    public function __construct(ClientBuilder $client, Filesystem $files)
    {
        parent::__construct();

        $this->client = $client;
        $this->connection = $this->getConnection();
        $this->files = $files;
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
     * @param int    $batch
     * @param string $mapping
     */
    protected function migrateMapping(int $batch, string $mapping):void
    {
        $this->connection->insert([
            'batch'   => $batch,
            'mapping' => $mapping,
        ]);
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
     *
     * @return array
     */
    protected function putMapping(SplFileInfo $mapping):void
    {
        $index = $this->getMappingName($mapping->getFileName(), true);

        $this->client->build()->indices()->create([
            'index' => $index,
            'body'  => json_decode($mapping->getContents(), true),
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
            $this->info("Indexing mapping: {$index}");

            try {
                // Create index.
                $this->putMapping($mapping);
            }
            catch (\Exception $exception) {
                $this->error("Failed to put mapping: {$index} because {$exception->getMessage()}");

                return;
            }

            $this->migrateMapping($batch, $index);

            if (!($command = $this->argument('artisan-command'))) {
                $command = config('laravel-elasticsearch.index_command');
            }

            // Begin indexing.
            $this->call($command, ['index' => $index]);

            $this->info("Indexed mapping: {$index}");

            if ($this->option('swap')) {
                $this->updateAlias($this->getMappingName($index, true));
            }

            $this->info("Migrated mapping: {$index}");
        }
    }
}
