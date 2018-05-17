<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\HasConnection;
use DesignMyNight\Elasticsearch\Console\Mappings\Traits\UpdatesAlias;
use Illuminate\Console\Command;

/**
 * Class MappingRollbackCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingRollbackCommand extends Command
{

    use HasConnection;
    use UpdatesAlias;

    /** @var string $description */
    protected $description = 'Rollback to the previous index';

    /** @var string $signature */
    protected $signature = 'index:rollback';

    /**
     * MappingRollbackCommand constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->connection = $this->getConnection();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $mappingMigrations = $this->connection->orderBy('mapping')->orderBy('batch')->get();

        if ($mappingMigrations->isEmpty()) {
            $this->info('Nothing to rollback.');

            return;
        }

        $latestBatch = $this->connection->max('batch');

        $mappingMigrations = $mappingMigrations->map(function (array $mapping):array {
            $mapping['alias'] = $this->stripTimestamp($mapping['mapping']);

            return $mapping;
        });

        $latestMigrations = $mappingMigrations->where('batch', $latestBatch);
        $previousMigrations = $mappingMigrations->where('batch', $latestBatch - 1);

        foreach ($latestMigrations as $migration) {
            if ($match = $previousMigrations->where('alias', $migration['alias'])->first()) {
                $this->info("Rolling back {$migration['mapping']} to {$match['mapping']}");
                $this->updateAlias($match['alias'], null, $migration);
                $this->info("Rolled back {$migration['mapping']}");
            }
        }

        $this->connection->where('batch', $latestBatch)->delete();
    }

    /**
     * @param string $mapping
     *
     * @return string
     */
    protected function stripTimestamp(string $mapping):string
    {
        return preg_replace('/[0-9_]+/', '', $mapping, 1);
    }
}