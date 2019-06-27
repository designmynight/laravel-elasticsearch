<?php

namespace DesignMyNight\Elasticsearch;

use DesignMyNight\Elasticsearch\Console\Mappings\IndexAliasCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\IndexCopyCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\IndexListCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\IndexRemoveCommand;
use DesignMyNight\Elasticsearch\Console\Mappings\IndexSwapCommand;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider;

/**
 * Class ElasticsearchServiceProvider
 *
 * @package DesignMyNight\Elasticsearch
 */
class ElasticsearchServiceProvider extends ServiceProvider
{

    /** @var array $commands */
    private $commands = [
        IndexAliasCommand::class,
        IndexCopyCommand::class,
        IndexListCommand::class,
        IndexRemoveCommand::class,
        IndexSwapCommand::class,
    ];

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

        $this->publishes([
            __DIR__ . '/Config/laravel-elasticsearch.php' => config_path('laravel-elasticsearch.php')
        ]);

        $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('elasticsearch', function ($config, $name) {
                $config['name'] = $name;
                return new Connection($config);
            });
        });

        $this->mergeConfigFrom(__DIR__ . '/Config/database.php', 'database');
    }
}
