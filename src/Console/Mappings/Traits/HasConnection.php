<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings\Traits;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Trait HasConnection
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings\Traits
 */
trait HasConnection
{

    /** @var Builder $connection */
    protected $connection;

    /**
     * @return Builder
     */
    protected function getConnection():Builder
    {
        return DB::connection()->table(config('laravel-elasticsearch.mappings_migration_table', 'mappings'));
    }
}
