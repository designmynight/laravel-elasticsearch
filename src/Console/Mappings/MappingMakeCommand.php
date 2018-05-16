<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

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
    protected $signature = 'make:mapping {name=mapping : Name of the mapping.} {--T|template= : Optional name of existing mapping as template.}';

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
        $mappingsPath = base_path('database/mappings');

        try {
            $this->resolveMappingsDirectory($mappingsPath);

            $mapping = $this->getPath($mappingsPath);
            $template = $this->files->get($this->getTemplate());

            $this->files->put($mapping, $template);
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
    protected function getPath(string $path):string
    {
        return "{$path}/{$this->getStub()}.json";
    }

    /**
     * @return string
     */
    protected function getStub():string
    {
        $name = $this->argument('name');
        $timestamp = date('Y_m_d_His');

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
     * @param string $path
     */
    private function resolveMappingsDirectory(string $path):void
    {
        if ($this->files->exists($path)) {
            return;
        }

        $this->files->makeDirectory($path, 0755, true);
    }
}
