<?php

namespace DesignMyNight\Elasticsearch\Database\Schema;

use Closure;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use DesignMyNight\Elasticsearch\Database\Schema\ColumnDefinition;

/**
 * Class Blueprint
 * @package DesignMyNight\Elasticsearch\Database\Schema
 */
class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    /** @var string */
    protected string $alias;

    /** @var string */
    protected string $document;

    /** @var array */
    protected array $meta = [];

    /** @var array */
    protected array $indexSettings = [];

    public function __construct(Connection $connection, $table, ?Closure $callback = null)
    {
        parent::__construct($connection, $table, $callback);
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
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function dateRange(string $column, array $parameters = [])
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
     * @param string $column
     * @param array $parameters
     *
     * @return ColumnDefinition
     */
    public function doubleRange(string $column, array $parameters = [])
    {
        return $this->range('double_range', $column, $parameters);
    }

    /**
     * @param bool|string $value
     */
    public function dynamic(bool|string $value): void
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
     * Create a new float column on the table.
     *
     * @param string $column
     * @param int $precision
     * @return ColumnDefinition
     */
    public function float($column, $precision = 53)
    {
        return $this->addColumn('float', $column);
    }

    /**
     * @param string $column
     * @param array $parameters
     *
     * @return ColumnDefinition
     */
    public function floatRange($column, array $parameters = [])
    {
        return $this->range('float_range', $column, $parameters);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function geoPoint(string $column, array $parameters = [])
    {
        return $this->addColumn('geo_point', $column, $parameters);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function geoShape(string $column, array $parameters = [])
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
    public function integer($column, $autoIncrement = false, $unsigned = false)
    {
        return $this->addColumn('integer', $column);
    }

    /**
     * @param string $column
     * @param array $parameters
     *
     * @return ColumnDefinition
     */
    public function integerRange(string $column, array $parameters = [])
    {
        return $this->range('integer_range', $column, $parameters);
    }

    /**
     * @param string $column
     * @return ColumnDefinition
     */
    public function ip(string $column)
    {
        return $this->ipAddress($column);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function ipRange(string $column, array $parameters = [])
    {
        return $this->range('ip_range', $column, $parameters);
    }

    /**
     * @param string $column
     * @param array  $relations
     *
     * @return ColumnDefinition
     */
    public function join(string $column, array $relations)
    {
        return $this->addColumn('join', $column, compact('relations'));
    }

    /**
     * @param string $column
     * @param array $parameters
     * @return ColumnDefinition
     */
    public function keyword(string $column, array $parameters = [])
    {
        return $this->addColumn('keyword', $column, $parameters);
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function long(string $column)
    {
        return $this->addColumn('long', $column);
    }

    /**
     * @param string $column
     * @param array  $parameters
     *
     * @return ColumnDefinition
     */
    public function longRange(string $column, array $parameters = [])
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
    public function nested(string $column)
    {
        return $this->addColumn('nested', $column);
    }

    /**
     * @param string $column
     * @return ColumnDefinition
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
    public function percolator(string $column, array $parameters = [])
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
    public function range(string $type, string $column, array $parameters = [])
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
    public function string($column, $length = null)
    {
        return $this->text($column);
    }

    /**
     * @param string $column
     * @return ColumnDefinition
     */
    public function text($column)
    {
        return $this->addColumn('text', $column);
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
