<?php

namespace DesignMyNight\Elasticsearch;

use DesignMyNight\Elasticsearch\Console\Mappings\MappingMakeCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\MappingMigrateCommand;
use Illuminate\Support\ServiceProvider;

class ElasticsearchServiceProvider extends ServiceProvider
{
    /** @var array $commands */
    private $commands = [
        MappingMakeCommand::class,
        MappingMigrateCommand::class
    ];

    /**
     * Register the service provider.
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('elasticsearch', function ($config) {
                return new Connection($config);
            });
        });
    }

    /**
     * Boot the service provider.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands($this->commands);
        }
    }
}
