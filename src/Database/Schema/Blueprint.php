<?php

namespace DesignMyNight\Elasticsearch\Database\Schema;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Grammars\Grammar;
use Illuminate\Support\Str;

/**
 * Class Blueprint
 * @package DesignMyNight\Elasticsearch\Database\Schema
 */
class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    /** @var string */
    protected $document;

    /**
     * @inheritDoc
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        $attributes = ['name'];

        if (isset($type)) {
            $attributes[] = 'type';
        }

        $this->columns[] = $column = new PropertyDefinition(
            array_merge(compact(...$attributes), $parameters)
        );

        return $column;
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function binary($name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('binary', $name, $parameters);
    }

    /**
     * Execute the blueprint against the database.
     *
     * @param \Illuminate\Database\Connection              $connection
     * @param \Illuminate\Database\Schema\Grammars\Grammar $grammar
     *
     * @return void
     */
    public function build(Connection $connection, Grammar $grammar)
    {
        foreach ($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement, [], $this);
        }
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function date($name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('date', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function dateRange(string $name, array $parameters = []): PropertyDefinition
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
     * @return PropertyDefinition
     */
    public function doubleRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('double_range', $name, $parameters);
    }

    /**
     * @param string $name
     *
     * @return PropertyDefinition
     */
    public function float($name, $total = 8, $places = 2): PropertyDefinition
    {
        return $this->addColumn('float', $name);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function floatRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('float_range', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function geoPoint(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('geo_point', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function geoShape(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('geo_shape', $name, $parameters);
    }

    /**
     * @return string|null
     */
    public function getDocument(): ?string
    {
        return $this->document ?? Str::singular($this->getTable());
    }

    /**
     * @param string $name
     *
     * @return PropertyDefinition
     */
    public function integer($name, $autoIncrement = false, $unsigned = false): PropertyDefinition
    {
        return $this->addColumn('integer', $name);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function integerRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('integer_range', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function ip(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->ipAddress($name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function ipAddress($name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('ip', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function ipRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('ip_range', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $relations
     *
     * @return PropertyDefinition
     */
    public function join(string $name, array $relations): PropertyDefinition
    {
        return $this->addColumn('join', $name, compact('relations'));
    }

    /**
     * @param string $name
     *
     * @return \Illuminate\Database\Schema\ColumnDefinition
     */
    public function keyword(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('keyword', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function longRange(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->range('long_range', $name, $parameters);
    }

    /**
     * @param string   $name
     * @param \Closure $parameters
     *
     * @return PropertyDefinition
     */
    public function nested(string $name): PropertyDefinition
    {
        return $this->addColumn('nested', $name);
    }

    /**
     * @param string   $name
     * @param \Closure $parameters
     *
     * @return PropertyDefinition|\Illuminate\Database\Schema\ColumnDefinition
     */
    public function object(string $name)
    {
        return $this->addColumn(null, $name);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function percolator(string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('percolator', $name, $parameters);
    }

    /**
     * @param string $type
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function range(string $type, string $name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn($type, $name, $parameters);
    }

    /**
     * @return string
     */
    public function statement(): string
    {
        return $this->getCommands()[0]->name;
    }

    /**
     * @param string $name
     * @param array  $length
     *
     * @return PropertyDefinition
     */
    public function string($name, $parameters = null): PropertyDefinition
    {
        return $this->text($name, $parameters ?? []);
    }

    /**
     * @param string     $name
     * @param array|null $parameters
     *
     * @return PropertyDefinition
     */
    public function text($name, array $parameters = []): PropertyDefinition
    {
        return $this->addColumn('text', $name, $parameters);
    }

    /**
     * @param string $name
     * @param array  $parameters
     *
     * @return PropertyDefinition
     */
    public function tokenCount(string $name, array $parameters = []): PropertyDefinition
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
