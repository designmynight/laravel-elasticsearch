<?php

namespace DesignMyNight\Elasticsearch\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Class CreateMappingsMigration
 *
 * @package DesignMyNight\Elasticsearch\Migrations
 */
class CreateMappingsMigration extends Migration
{

    /** @var string $table */
    private $table;

    /**
     * CreateMappingsMigration constructor.
     */
    public function __construct()
    {
        $this->table = config('laravel-elasticsearch.mappings_migration_table', 'mappings');
    }

    /**
     * Run migration.
     */
    public function up()
    {
        Schema::create($this->table, function (Blueprint $table):void {
            $table->integer('batch');
            $table->string('mapping');
        });
    }

    /**
     * Rollback migration.
     */
    public function down()
    {
        Schema::drop($this->table);
    }
}