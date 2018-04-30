<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use League\Flysystem\Config;

/**
 * Class MappingMakeCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingMakeCommand extends Command
{
    /** @var string $signature */
    protected $signature = 'make:mapping {name=mapping : Name of the mapping.}';

    /** @var string $description */
    protected $description = 'Create new mapping.';

    /** @var Config $config */
    protected $config;

    /** @var Filesystem $files */
    protected $files;

    /**
     * MappingMakeCommand constructor.
     *
     * @param Filesystem $files
     */
    public function __construct(Config $config, Filesystem $files)
    {
        parent::__construct();

        $this->config = $config;
        $this->files = $files;
    }

    /**
     * @return void
     */
    public function handle()
    {
        $mappingsPath = 'base_path/database/mappings';

        try {
            if (!$this->files->exists($mappingsPath)) {
                $this->files->makeDirectory($mappingsPath, 0755, true);
            }

            $mapping = $this->makeFileName();
            $stub = $this->files->get('base_path/vendor/designmynight/laravel-elasticsearch/src/Console/Mappings/stubs/mapping.stub');

            $this->files->put("{$mappingsPath}/{$mapping}.json", $this->files->get($stub));
        }
        catch (\Exception $exception) {
            $this->error($exception->getMessage());

            return;
        }

        $this->info("Mapping {$mapping} created successfully.");
    }

    /**
     * @return string
     */
    protected function makeFileName():string
    {
        $name = $this->option('name');

        if ($environment = $this->config->get('database.elasticsearch.suffix', null)) {
            $name .= "_{$environment}";
        }

        $timestamp = Carbon::now()->getTimestamp();

        return "{$name}_{$timestamp}";
    }
}
