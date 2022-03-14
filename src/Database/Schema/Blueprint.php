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
     * @param  string  $type
     * @param  string  $name
     * @param  array  $parameters
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function addColumn($type, $name, array $parameters = [])
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
     * @param  string  $column
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function binary($name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('binary', $name, $parameters);
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
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function date($name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn('date', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function dateRange(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->range('date_range', $name, $parameters);
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
    public function doubleRange(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->range('double_range', $name, $parameters);
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
     * @param string $name
     *
     * @return ColumnDefinition
     */
    public function float($name, $total = 8, $places = 2, $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('float', $name);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function floatRange(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->range('float_range', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function geoPoint(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn('geo_point', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function geoShape(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn('geo_shape', $name, $parameters);
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
     * @param string $name
     *
     * @return ColumnDefinition
     */
    public function integer($name, $autoIncrement = false, $unsigned = false): ColumnDefinition
    {
        return $this->addColumn('integer', $name);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function integerRange(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->range('integer_range', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function ip(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->ipAddress($name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function ipAddress($name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn('ip', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function ipRange(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->range('ip_range', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $relations
     *
     * @return ColumnDefinition
     */
    public function join(string $name, array $relations): ColumnDefinition
    {
        return $this->addColumn('join', $name, compact('relations'));
    }

    /**
     * @param string $name
     *
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function keyword(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn('keyword', $name, $parameters);
    }

    /**
     * @param string $name
     *
     * @return ColumnDefinition
     */
    public function long(string $name):ColumnDefinition
    {
        return $this->addColumn('long', $name);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function longRange(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->range('long_range', $name, $parameters);
    }

    /**
     * @param array $meta
     */
    public function meta(array $meta): void
    {
        $this->addMetaField('_meta', $meta);
    }

    /**
     * @param string   $name
     * @param \Closure $parameters
     *
     * @return ColumnDefinition
     */
    public function nested(string $name): ColumnDefinition
    {
        return $this->addColumn('nested', $name);
    }

    /**
     * @param string   $name
     * @param \Closure $parameters
     *
     * @return ColumnDefinition|\Illuminate\Database\Schema\ColumnDefinition
     */
    public function object(string $name)
    {
        return $this->addColumn(null, $name);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function percolator(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn('percolator', $name, $parameters);
    }

    /**
     * @param string $type
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function range(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn($type, $name, $parameters);
    }

    /**
     * @return void
     */
    public function routingRequired(): void
    {
        $this->addMetaField('_routing', ['required' => true]);
    }

    /**
     * @param string $name
     * @param array  $length
     *
     * @return ColumnDefinition
     */
    public function string($name, $parameters = null): ColumnDefinition
    {
        return $this->text($name, $parameters ?? []);
    }

    /**
     * @param string     $name
     * @param array|null $parameters
     *
     * @return ColumnDefinition
     */
    public function text($name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn('text', $name, $parameters);
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
     * @param string $name
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function tokenCount(string $name, array $parameters = []): ColumnDefinition
    {
        return $this->addColumn('token_count', $name, $parameters);
    }

    /**
     * @return \Illuminate\Support\Fluent
     */
    public function update()
    {
        return $this->addCommand('update');
    }
}
