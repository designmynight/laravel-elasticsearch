<?php

namespace DesignMyNight\Elasticsearch;

use Closure;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Str;

class QueryBuilder extends BaseBuilder
{
    public $type;

    public $filters;

    public $postFilters;

    public $aggregations;

    protected $rawResponse;

    protected $scrollSelect;

    /**
     * Set the document type the search is targeting.
     *
     * @param string $type
     *
     * @return Builder
     */
    public function type($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'Between';

        $this->wheres[] = compact('column', 'values', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param  string  $column
     * @param  array   $coords
     * @param  string  $distance
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereGeoDistance($column, array $location, $distance, $boolean = 'and', $not = false)
    {
        $type = 'GeoDistance';

        $this->wheres[] = compact('column', 'location', 'distance', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param  string  $column
     * @param  array   $bounds
     * @return $this
     */
    public function whereGeoBoundsIn($column, array $bounds)
    {
        $type = 'GeoBoundsIn';

        $this->wheres[] = [
            'column' => $column,
            'bounds' => $bounds,
            'type' => 'GeoBoundsIn',
            'boolean' => 'and',
            'not' => false
        ];

        return $this;
    }

    /**
     * Add a "where date" statement to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereDate($column, $operator, $value = null, $boolean = 'and', $not = false)
    {
        list($value, $operator) = $this->prepareValueAndOperator(
            $value, $operator, func_num_args() == 2
        );

        $type = 'Date';

        $this->wheres[] = compact('column', 'operator', 'value', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a 'nested document' statement to the query.
     *
     * @param  string  $column
     * @param  \Illuminate\Database\Query\Builder|static $query
     * @param  string  $boolean
     * @return $this
     */
    public function whereNestedDoc($column, $query, $boolean = 'and')
    {
        $type = 'NestedDoc';

        if ( $query instanceof Closure ){
            call_user_func($query, $query = $this->newQuery());
        }

        $this->wheres[] = compact('column', 'query', 'type', 'boolean');

        return $this;
    }

    /**
     * Add another query builder as a nested where to the query builder.
     *
     * @param  \Illuminate\Database\Query\Builder|static $query
     * @param  string  $boolean
     * @return $this
     */
    public function addNestedWhereQuery($query, $boolean = 'and')
    {
        $type = 'Nested';

        $compiled = compact('type', 'query', 'boolean');

        if (count($query->wheres)) {
            $this->wheres[] = $compiled;
        }

        if (count($query->filters)) {
            $this->filters[] = $compiled;
        }

        return $this;
    }

    /**
     * Add any where clause with given options.
     *
     * @return $this
     */
    public function whereWithOptions(...$args)
    {
        $options = array_pop($args);
        $type = array_shift($args);
        $method = $type == 'Basic' ? 'where' : 'where' . $type;

        $this->$method(...$args);

        $this->wheres[count($this->wheres) -1]['options'] = $options;

        return $this;
    }

    /**
     * Add a filter query by calling the required 'where' method
     * and capturing the added where as a filter
     */
    public function dynamicFilter($method, $args){
        $method = lcfirst(substr($method, 6));

        $numWheres = count($this->wheres);

        $this->$method(...$args);

        $filterType = array_pop($args) === 'postFilter' ? 'postFilters' : 'filters';

        if ( count($this->wheres) > $numWheres ){
            $this->$filterType[] = array_pop($this->wheres);
        }

        return $this;
    }

    /**
     * Add a text search clause to the query.
     *
     * @param  string  $query
     * @param  array  $options
     * @param  string  $boolean
     * @return $this
     */
    public function search($query, $options = [], $boolean = 'and')
    {
        $this->wheres[] = [
            'type'    => 'Search',
            'value'   => $query,
            'boolean' => $boolean,
            'options' => $options,
        ];

        return $this;
    }

    /**
     * Add a parent where statement to the query.
     *
     * @param  \Closure $callback
     * @param  string   $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereParent($documentType, $query, $boolean = 'and')
    {
        $this->wheres[] = [
            'type' => 'Parent',
            'documentType' => $documentType,
            'value' => $query,
            'boolean' => $boolean
        ];

        return $this;
    }

    /**
     * Add a child where statement to the query.
     *
     * @param  \Closure $callback
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  string   $boolean
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereChild($documentType, $query, $boolean = 'and')
    {
        $this->wheres[] = [
            'type' => 'Child',
            'documentType' => $documentType,
            'value' => $query,
            'boolean' => $boolean
        ];

        return $this;
    }

    /**
     * @param      $key
     * @param      $type
     * @param null $args
     * @param null $aggregations
     */
    public function aggregation($key, $type, $args = null, $aggregations = null)
    {
        if ( $args instanceof Closure ){
            call_user_func($args, $args = $this->newQuery());
        }

        if ( $aggregations instanceof Closure ){
            call_user_func($aggregations, $aggregations = $this->newQuery());
        }

        $this->aggregations[] = compact(
            'key', 'type', 'args', 'aggregations'
        );
    }

    public function orderBy($column, $direction = 1, $options = null)
    {
        if (is_string($direction)) {
            $direction = strtolower($direction) == 'asc' ? 1 : -1;
        }

        $type = isset($options['type']) ? $options['type'] : 'basic';

        $this->orders[] = compact('column', 'direction', 'type', 'options');

        return $this;
    }

    public function getAggregationResults(){
        return $this->processor->getAggregationResults();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Support\Collection|Generator
     */
    public function get($columns = ['*'])
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $results = $this->processor->processSelect($this, $this->runSelect());

        $this->columns = $original;

        return $this->shouldUseScroll() ? $results : collect($results);
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect()
    {
        if ($this->shouldUseScroll()){
            $this->rawResponse = $this->connection->scrollSelect($this->toCompiledQuery());
        }
        else {
            $this->rawResponse = $this->connection->select($this->toCompiledQuery());
        }

        return $this->rawResponse;
    }

    /**
     * Determine whether to use an Elasticsearch scroll cursor for the query.
     *
     * @return self
     */
    public function usingScroll(bool $useScroll = true): self
    {
        $this->scrollSelect = $useScroll;

        return $this;
    }

    /**
     * Determine whether to use an Elasticsearch scroll cursor for the query.
     *
     * @return bool
     */
    public function shouldUseScroll(): bool
    {
        return !!$this->scrollSelect;
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param  array  $columns
     * @return int
     */
    public function getCountForPagination($columns = ['*'])
    {
        if ($rawResponse = $this->processor->getRawResponse()) {
            return $rawResponse['hits']['total'];
        }
    }

    /**
     * Get the time it took Elasticsearch to perform the query
     *
     * @return int time in milliseconds
     */
    public function getSearchDuration()
    {
        if ($rawResponse = $this->processor->getRawResponse()) {
            return $rawResponse['took'];
        }
    }

    /**
     * Get the Elasticsearch representation of the query.
     *
     * @return string
     */
    public function toCompiledQuery()
    {
        return $this->toSql();
    }

    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'filterWhere')) {
            return $this->dynamicFilter($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }
}
