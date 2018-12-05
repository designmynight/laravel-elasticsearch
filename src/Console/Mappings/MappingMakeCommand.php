<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Class MappingMakeCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class MappingMakeCommand extends Command
{

    /** @var string $description */
    protected $description = 'Create new mapping.';

    /** @var Filesystem $files */
    protected $files;

    /** @var string $signature */
    protected $signature = 'make:mapping {name : Name of the mapping.} {--T|template= : Optional name of existing mapping as template.} {--U|update : Update existing index}';

    /**
     * MappingMakeCommand constructor.
     *
     * @param Filesystem $files
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        $this->files = $files;
    }

    /**
     * @return void
     */
    public function handle()
    {
        try {
            $this->resolveMappingsDirectory();

            $mapping = $this->getPath();
            $template = $this->files->get($this->getTemplate());

            $this->files->put($mapping, $template);
        } catch (\Exception $exception) {
            $this->error($exception->getMessage());

            return;
        }

        $this->info("Mapping {$mapping} created successfully.");
    }

    /**
     * @return string
     */
    protected function getPath():string
    {
        return base_path("database/mappings/{$this->getStub()}.json");
    }

    /**
     * @return string
     */
    protected function getStub():string
    {
        $name = $this->argument('name');
        $timestamp = Carbon::now()->format('Y_m_d_His');

        if ($this->option('update')) {
            $timestamp .= "_update";
        }

        return "{$timestamp}_{$name}";
    }

    /**
     * @return string
     */
    protected function getTemplate():string
    {
        if ($template = $this->option('template')) {
            if (!str_contains($template, '.json')) {
                $template .= '.json';
            }

            return base_path("database/mappings/{$template}");
        }

        return base_path('vendor/designmynight/laravel-elasticsearch/src/Console/Mappings/stubs/mapping.stub');
    }

    /**
     * @return void
     */
    private function resolveMappingsDirectory():void
    {
        $path = base_path('database/mappings');

        if ($this->files->exists($path)) {
            return;
        }

        $this->files->makeDirectory($path, 0755, true);
    }
}
