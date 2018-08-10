<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Console\Mappings\Traits\GetsIndices;
use Exception;
use Illuminate\Filesystem\Filesystem;

/**
 * Class MappingMakeCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class AliasMakeCommand extends Command
{

    use GetsIndices;

    /** @var string $description */
    protected $description = 'Create new alias.';

    /** @var Filesystem $files */
    protected $files;

    /** @var string $signature */
    protected $signature = 'make:mapping-alias {name : Name of the mapping alias.} {index? : Name of index to point to}';

    /**
     * @return void
     */
    public function handle()
    {
        try {
            $aliasName = $this->argument('name');
            $indexName = $this->getIndexName();

            if($this->client->indices()->existsAlias(['name' => $aliasName])){
                throw new Exception("Alias $aliasName already exists");
            }

            $this->client->indices()->putAlias([
              'index' => $indexName,
              'name' => $aliasName
            ]);
        }
        catch (Exception $exception) {
            $this->error($exception->getMessage());

            return;
        }

        $this->info("Alias $aliasName created successfully.");
    }

    /**
     * @return string
     */
    protected function getIndexName():string
    {
        if (!$indexName = $this->argument('index')) {
            $indices = collect($this->getIndices())
              ->sortBy('index')
              ->pluck('index')
              ->toArray();

            $indexName = $this->choice('Which index do you want to create an alias for?', $indices);
        }

        return $indexName;
    }
}
