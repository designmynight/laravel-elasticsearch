<?php

namespace DesignMyNight\Elasticsearch\Database\Schema;

use Carbon\Carbon;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\ColumnDefinition;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * Class Blueprint
 * @package DesignMyNight\Elasticsearch\Database\Schema
 */
class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    /** @var string */
    protected $alias;

    /** @var string */
    protected $document;

    /** @var array */
    protected $meta = [];

    /** @var array */
    protected $indexSettings = [];

    /**
     * Add a new column to the blueprint.
     *
     * @param string $type
     * @param string $name
     * @param  array  $parameters
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function addColumn(string $type, string $name, array $parameters = [])
    {
        return $this->addColumnDefinition(new ColumnDefinition(
            array_merge(compact('type', 'name'), $parameters)
        ));
    }

    /**
     * Add a new column definition to the blueprint.
     *
     * @param  \Illuminate\Database\Schema\ColumnDefinition  $definition
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    protected function addColumnDefinition($definition)
    {
        $this->columns[] = $definition;

        if ($this->after) {
            $definition->after($this->after);

            $this->after = $definition->name;
        }

        return $definition;
    }

    /**
     * @param string $key
     * @param array  $value
     */
    public function addIndexSettings(string $key, array $value): void
    {
        $this->indexSettings[$key] = $value;
    }

    /**
     * @param string $key
     * @param        $value
     */
    public function addMetaField(string $key, $value): void
    {
        $this->meta[$key] = $value;
    }

    /**
     * @param string $alias
     */
    public function alias(string $alias): void
    {
        $this->alias = $alias;
    }

    /**
     * Create a new binary column on the table.
     *
     * @param string $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function binary(string $column): PropertyDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Execute the blueprint against the database.
     *
     * @param  \Illuminate\Database\Connection  $connection
     * @param  \Illuminate\Database\Schema\Grammars\Grammar  $grammar
     * @return void
     */
    public function build(Connection $connection, Grammar $grammar)
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement);
        }
    }


    /**
     * @param string $column
     * @param array $parameters
     *
     * @return ColumnDefinition
     */
    public function date(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('date', $column, $parameters);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function dateRange(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->range('date_range', $column, $parameters);
    }

    /**
     * @param string $name
     */
    public function document(string $name): void
    {
        $this->document = $name;
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function doubleRange(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->range('double_range', $column, $parameters);
    }

    /**
     * @param bool|string $value
     */
    public function dynamic($value): void
    {
        $this->addMetaField('dynamic', $value);
    }

    /**
     * @return void
     */
    public function enableAll(): void
    {
        $this->addMetaField('_all', ['enabled' => true]);
    }

    /**
     * @return void
     */
    public function enableFieldNames(): void
    {
        $this->addMetaField('_field_names', ['enabled' => true]);
    }

    /**
     * @param string $column
     * @param int $total
     * @param int $places
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function float($column, $total = 8, $places = 2, $unsigned = false): PropertyDefinition
    {
        return $this->addColumn('float', $column);
    }

    /**
     * @param string $column
     * @param array $parameters
     *
     * @return ColumnDefinition
     */
    public function floatRange(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->range('float_range', $column, $parameters);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function geoPoint(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('geo_point', $column, $parameters);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function geoShape(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('geo_shape', $column, $parameters);
    }

    /**
     * @return string
     */
    public function getAlias(): string
    {
        return ($this->alias ?? $this->getTable()) . Config::get('database.connections.elasticsearch.suffix');
    }

    /**
     * @return string
     */
    public function getDocumentType(): string
    {
        return $this->document ?? Str::singular($this->getTable());
    }

    /**
     * @return string
     */
    public function getIndex(): string
    {
        $suffix = Config::get('database.connections.elasticsearch.suffix');
        $timestamp = Carbon::now()->format('Y_m_d_His');

        return "{$timestamp}_{$this->getTable()}" . $suffix;
    }

    /**
     * @return array
     */
    public function getIndexSettings(): array
    {
        return $this->indexSettings;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        return $this->meta;
    }

    /**
     * @param string column
     * @param bool $autoIncrement
     * @param bool $unsigned
     * @return ColumnDefinition
     */
    public function integer(string $column, $autoIncrement = false, $unsigned = false): PropertyDefinition
    {
        return $this->addColumn('integer', $column);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function integerRange(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->range('integer_range', $column, $parameters);
    }

    /**
     * @param string $name
     * @return ColumnDefinition
     */
    public function ip(string $column): PropertyDefinition
    {
        return $this->ipAddress($column);
    }

    /**
     * @param string $column
     * @return ColumnDefinition
     */
    public function ipAddress(string $column = 'ip_address'): PropertyDefinition
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function ipRange(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->range('ip_range', $column, $parameters);
    }

    /**
     * @param string $column
     * @param array  $relations
     *
     * @return ColumnDefinition
     */
    public function join(string $column, array $relations): PropertyDefinition
    {
        return $this->addColumn('join', $column, compact('relations'));
    }

    /**
     * @param string $column
     * @param array $parameters
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function keyword(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('keyword', $column, $parameters);
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function long(string $column):ColumnDefinition
    {
        return $this->addColumn('long', $column);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function longRange(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->range('long_range', $column, $parameters);
    }

    /**
     * @param array $meta
     */
    public function meta(array $meta): void
    {
        $this->addMetaField('_meta', $meta);
    }

    /**
     * @param string   $column
     * @param \Closure $parameters
     *
     * @return ColumnDefinition
     */
    public function nested(string $column): PropertyDefinition
    {
        return $this->addColumn('nested', $column);
    }

    /**
     * @param string $column
     * @return ColumnDefinition|\Illuminate\Database\Schema\ColumnDefinition
     */
    public function object(string $column)
    {
        return $this->addColumn(null, $column);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function percolator(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('percolator', $column, $parameters);
    }

    /**
     * @param string $type
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function range(string $type, string $column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn($type, $column, $parameters);
    }

    /**
     * @return void
     */
    public function routingRequired(): void
    {
        $this->addMetaField('_routing', ['required' => true]);
    }

    /**
     * @param string $column
     * @param array  $length
     *
     * @return ColumnDefinition
     */
    public function string($column, $length = null): PropertyDefinition
    {
        $length = $length ?: Builder::$defaultStringLength;

        return $this->text('string', $column,  compact('length'));
    }

    /**
     * @param string $column
     * @param string $type
     * @param array|null $parameters
     *
     * @return ColumnDefinition
     */
    public function text(string $type, string $column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn($type, $column, $parameters);
    }

    /**
     * @param Connection $connection
     * @param Grammar    $grammar
     * @return \Closure[]
     */
    public function toSql(Connection $connection, Grammar $grammar)
    {
        $this->addImpliedCommands($grammar);

        $statements = [];

        // Each type of command has a corresponding compiler function on the schema
        // grammar which is used to build the necessary SQL statements to build
        // the blueprint element, so we'll just call that compilers function.
        $this->ensureCommandsAreValid($connection);

        foreach ($this->commands as $command) {
            $method = 'compile' . ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if (!is_null($statement = $grammar->$method($this, $command, $connection))) {
                    $statements[] = $statement;
                }
            }
        }

        return $statements;
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function tokenCount(string $column, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('token_count', $column, $parameters);
    }

    /**
     * @return \Illuminate\Support\Fluent
     */
    public function update()
    {
        return $this->addCommand('update');
    }
}
