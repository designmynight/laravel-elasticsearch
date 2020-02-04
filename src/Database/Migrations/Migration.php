<?php


namespace DesignMyNight\Elasticsearch\Database\Migrations;

use Illuminate\Support\Facades\Schema;

class Migration extends \Illuminate\Database\Migrations\Migration
{
    /** @var \Illuminate\Database\Schema\Builder */
    private $schema;

    public function __construct()
    {
        $this->schema = Schema::connection('elasticsearch');
    }
}
