<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\GetsIndices;
use Exception;
use Illuminate\Filesystem\Filesystem;

/**
 * Class IndexAliasCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexAliasCommand extends Command
{
    use GetsIndices;

    /** @var string $description */
    protected $description = 'Create new alias.';

    /** @var Filesystem $files */
    protected $files;

    /** @var string $signature */
    protected $signature = 'index:alias {name : Name of the alias.} {index? : Name of index to point to}';

    /**
     * @return void
     */
    public function handle()
    {
        $alias = $this->argument('name');
        $index = $this->getIndexName();

        try {
            if ($this->client->indices()->existsAlias(['name' => $alias])) {
                throw new Exception("Alias $alias already exists");
            }

            $this->client->indices()->putAlias([
                'index' => $index,
                'name' => $alias
            ]);
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

            return;
        }

        $this->info("Alias $alias created successfully.");
    }

    /**
     * @return string
     */
    protected function getIndexName(): string
    {
        if (!$index = $this->argument('index')) {
            $indices = collect($this->getIndices())
                ->sortBy('index')
                ->pluck('index')
                ->toArray();

            $index = $this->choice('Which index do you want to create an alias for?', $indices);
        }

        return $index;
    }
}
