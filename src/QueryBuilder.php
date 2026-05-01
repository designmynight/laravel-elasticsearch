<?php

namespace DesignMyNight\Elasticsearch;

use Closure;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Str;

/**
 * Class QueryBuilder
 * @method $this filterWhere(string|Closure $column, string $operator = null, mixed $value = null, string $boolean = null)
 * @method $this filterWhereGeoBoundsIn($column, array $bounds)
 * @method $this filterWhereGeoDistance($column, array $location, $distance, $boolean = 'and', bool $not = false)
 * @method $this filterWhereIn($column, $values, $boolean = 'and', $not = false)
 *
 * @package DesignMyNight\Elasticsearch
 */
class QueryBuilder extends BaseBuilder
{
    /** @var string[] */
    public const DELETE_REFRESH = [
        'FALSE' => false,
        'TRUE' => true,
    ];

    /** @var string[] */
    public const DELETE_CONFLICT = [
        'ABORT' => 'abort',
        'PROCEED' => 'proceed',
    ];

    public $type;

    public $filters;

    public $postFilters;

    public $aggregations;

    public $includeInnerHits;

    public $distinct;

    protected $parentId;

    protected $results;

    /** @var int */
    protected $resultsOffset;

    protected $rawResponse;

    protected $routing;

    /** @var mixed[] */
    protected $options;

    /**
     * All of the supported clause operators.
     *
     * @var array
     */
    public $operators = ['=', '<', '>', '<=', '>=', '!=', 'exists', 'like'];

    /**
     * Set the document type the search is targeting.
     *
     * @param string $type
     *
     * @return Builder
     */
    public function type($type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Set the parent ID to be used when routing queries to Elasticsearch
     *
     * @param string $id
     * @return Builder
     */
    public function parentId(string $id): self
    {
        $this->parentId = $id;

        return $this;
    }

    /**
     * Get the parent ID to be used when routing queries to Elasticsearch
     *
     * @return string|null
     */
    public function getParentId(): ?string
    {
        return $this->parentId;
    }

    /**
     * @param string $routing
     * @return QueryBuilder
     */
    public function routing(string $routing): self
    {
        $this->routing = $routing;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRouting(): ?string
    {
        return $this->routing;
    }

    /**
     * @return mixed|null
     */
    public function getOption(string $option)
    {
        return $this->options[$option] ?? null;
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return $this
     */
    public function distinct()
    {
        $columns = func_get_args();

        if (count($columns) > 0) {
            $this->distinct = is_array($columns[0])  ? $columns[0] : $columns;
        } else {
            $this->distinct = [];
        }

        return $this;
    }

    /**
     * Add a where between statement to the query.
     *
     * @param string $column
     * @param array  $values
     * @param string $boolean
     * @param bool   $not
     * @return self
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false): self
    {
        $type = 'Between';

        $this->wheres[] = compact('column', 'values', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param string $column
     * @param array  $coords
     * @param string $distance
     * @param string $boolean
     * @param bool   $not
     * @return self
     */
    public function whereGeoDistance($column, array $location, $distance, $boolean = 'and', bool $not = false): self
    {
        $type = 'GeoDistance';

        $this->wheres[] = compact('column', 'location', 'distance', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a 'distance from point' statement to the query.
     *
     * @param string $column
     * @param array  $bounds
     * @return self
     */
    public function whereGeoBoundsIn($column, array $bounds): self
    {
        $type = 'GeoBoundsIn';

        $this->wheres[] = [
            'column'  => $column,
            'bounds'  => $bounds,
            'type'    => 'GeoBoundsIn',
            'boolean' => 'and',
            'not'     => false,
        ];

        return $this;
    }

    /**
     * Add a "where date" statement to the query.
     *
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @param string $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereDate($column, $operator, $value = null, $boolean = 'and', $not = false): self
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() == 2
        );

        $type = 'Date';

        $this->wheres[] = compact('column', 'operator', 'value', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a 'nested document' statement to the query.
     *
     * @param string                                    $column
     * @param callable|\Illuminate\Database\Query\Builder|static $query
     * @param string                                    $boolean
     * @return self
     */
    public function whereNestedDoc($column, $query, $boolean = 'and'): self
    {
        $type = 'NestedDoc';

        if (!is_string($query) && is_callable($query)) {
            call_user_func($query, $query = $this->newQuery());
        }

        $this->wheres[] = compact('column', 'query', 'type', 'boolean');

        return $this;
    }

    /**
     * Add a 'must not' statement to the query.
     *
     * @param \Illuminate\Database\Query\Builder|static $query
     * @param string                                    $boolean
     * @return self
     */
    public function whereNot($query, $boolean = 'and'): self
    {
        $type = 'Not';

        call_user_func($query, $query = $this->newQuery());

        $this->wheres[] = compact('query', 'type', 'boolean');

        return $this;
    }

    /**
     * Add a prefix query
     *
     * @param string  $column
     * @param string  $value
     * @param string  $boolean
     * @param boolean $not
     * @return self
     */
    public function whereStartsWith($column, string $value, $boolean = 'and', $not = false): self
    {
        $type = 'Prefix';

        $this->wheres[] = compact('column', 'value', 'type', 'boolean', 'not');

        return $this;
    }

    /**
     * Add a script query
     *
     * @param string $script
     * @param array  $options
     * @param string $boolean
     * @return self
     */
    public function whereScript(string $script, array $options = [], $boolean = 'and'): self
    {
        $type = 'Script';

        $this->wheres[] = compact('script', 'options', 'type', 'boolean');

        return $this;
    }

    /**
     * Add a "where weekday" statement to the query.
     *
     * @param string                    $column
     * @param string                    $operator
     * @param \DateTimeInterface|string $value
     * @param string                    $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereWeekday($column, $operator, $value = null, $boolean = 'and')
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        if ($value instanceof DateTimeInterface) {
            $value = $value->format('N');
        }

        return $this->addDateBasedWhere('Weekday', $column, $operator, $value, $boolean);
    }

    /**
     * Add an "or where weekday" statement to the query.
     *
     * @param string                    $column
     * @param string                    $operator
     * @param \DateTimeInterface|string $value
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function orWhereWeekday($column, $operator, $value = null)
    {
        [$value, $operator] = $this->prepareValueAndOperator(
            $value,
            $operator,
            func_num_args() === 2
        );

        return $this->addDateBasedWhere('Weekday', $column, $operator, $value, 'or');
    }

    /**
     * Add a date based (year, month, day, time) statement to the query.
     *
     * @param string $type
     * @param string $column
     * @param string $operator
     * @param mixed  $value
     * @param string $boolean
     * @return $this
     */
    protected function addDateBasedWhere($type, $column, $operator, $value, $boolean = 'and')
    {
        switch ($type) {
            case 'Year':
                $dateType = 'year';
                break;

            case 'Month':
                $dateType = 'monthOfYear';
                break;

            case 'Day':
                $dateType = 'dayOfMonth';
                break;

            case 'Weekday':
                $dateType = 'dayOfWeek';
                break;
        }

        $type = 'Script';

        $operator = $operator == '=' ? '==' : $operator;

        $script = "doc.{$column}.size() > 0 && doc.{$column}.date.{$dateType} {$operator} params.value";

        $options['params'] = ['value' => (int)$value];

        $this->wheres[] = compact('script', 'options', 'type', 'boolean');

        return $this;
    }

    /**
     * Add another query builder as a nested where to the query builder.
     *
     * @param \Illuminate\Database\Query\Builder|static $query
     * @param string                                    $boolean
     * @return self
     */
    public function addNestedWhereQuery($query, $boolean = 'and'): self
    {
        $type = 'Nested';

        $compiled = compact('type', 'query', 'boolean');

        if (count($query->wheres)) {
            $this->wheres[] = $compiled;
        }

        if (isset($query->filters) && count($query->filters)) {
            $this->filters[] = $compiled;
        }

        return $this;
    }

    /**
     * Add any where clause with given options.
     *
     * @return self
     */
    public function whereWithOptions(...$args): self
    {
        $options = array_pop($args);
        $type = array_shift($args);
        $method = $type == 'Basic' ? 'where' : 'where' . $type;

        $this->$method(...$args);

        $this->wheres[count($this->wheres) - 1]['options'] = $options;

        return $this;
    }

    /**
     * Add a filter query by calling the required 'where' method
     * and capturing the added where as a filter
     *
     * @param string $method
     * @param array  $args
     * @return self
     */
    public function dynamicFilter(string $method, array $args): self
    {
        $method = lcfirst(substr($method, 6));

        $numWheres = count($this->wheres);

        $this->$method(...$args);

        $filterType = array_pop($args) === 'postFilter' ? 'postFilters' : 'filters';

        if (count($this->wheres) > $numWheres) {
            $this->$filterType[] = array_pop($this->wheres);
        }

        return $this;
    }

    /**
     * Add a text search clause to the query.
     *
     * @param string $query
     * @param array  $options
     * @param string $boolean
     * @return self
     */
    public function search($query, $options = [], $boolean = 'and'): self
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
     * @param string $parentType Name of the parent relation from the join mapping
     * @param mixed  $id
     * @param string $boolean
     * @return QueryBuilder
     */
    public function whereParentId(string $parentType, $id, string $boolean = 'and'): self
    {
        $this->wheres[] = [
            'type' => 'ParentId',
            'parentType' => $parentType,
            'id' => $id,
            'boolean' => $boolean,
        ];

        return $this;
    }

    /**
     * Add a where parent statement to the query.
     *
     * @param string   $documentType
     * @param \Closure $callback
     * @param array    $options
     * @param string   $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereParent(
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        return $this->whereRelationship('parent', $documentType, $callback, $options, $boolean);
    }

    /**
     * Add a where child statement to the query.
     *
     * @param string   $documentType
     * @param \Closure $callback
     * @param array    $options
     * @param string   $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereChild(
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        return $this->whereRelationship('child', $documentType, $callback, $options, $boolean);
    }

    /**
     * Add a where relationship statement to the query.
     *
     * @param string   $relationshipType
     * @param string   $documentType
     * @param \Closure $callback
     * @param array    $options
     * @param string   $boolean
     *
     * @return \Illuminate\Database\Query\Builder|static
     */
    protected function whereRelationship(
        string $relationshipType,
        string $documentType,
        Closure $callback,
        array $options = [],
        string $boolean = 'and'
    ): self {
        call_user_func($callback, $query = $this->newQuery());

        $this->wheres[] = [
            'type'         => ucfirst($relationshipType),
            'documentType' => $documentType,
            'value'        => $query,
            'options'      => $options,
            'boolean'      => $boolean,
        ];

        return $this;
    }

    /**
     * @param string $key
     * @param string $type
     * @param null   $args
     * @param null   $aggregations
     * @return self
     */
    public function aggregation($key, $type = null, $args = null, $aggregations = null): self
    {
        if ($key instanceof Aggregation) {
            $aggregation = $key;

            $this->aggregations[] = [
                'key'          => $aggregation->getKey(),
                'type'         => $aggregation->getType(),
                'args'         => $aggregation->getArguments(),
                'aggregations' => $aggregation($this->newQuery()),
            ];

            return $this;
        }

        if (!is_string($args) && is_callable($args)) {
            call_user_func($args, $args = $this->newQuery());
        }

        if (!is_string($aggregations) && is_callable($aggregations)) {
            call_user_func($aggregations, $aggregations = $this->newQuery());
        }

        $this->aggregations[] = compact(
            'key',
            'type',
            'args',
            'aggregations'
        );

        return $this;
    }

    /**
     * @param string $column
     * @param int    $direction
     * @param array  $options
     * @return self
     */
    public function orderBy($column, $direction = 1, $options = null): self
    {
        if (is_string($direction)) {
            $direction = strtolower($direction) == 'asc' ? 1 : -1;
        }

        $type = isset($options['type']) ? $options['type'] : 'basic';

        $this->orders[] = compact('column', 'direction', 'type', 'options');

        return $this;
    }

    /**
     * Whether to include inner hits in the response
     *
     * @return  self
     */
    public function withInnerHits(): self
    {
        $this->includeInnerHits = true;

        return $this;
    }

    /**
     * Set whether to refresh during delete by query
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/7.x/docs-delete-by-query.html#docs-delete-by-query-api-query-params
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/7.x/docs-delete-by-query.html#_refreshing_shards
     *
     * @param string $option
     * @return self
     * @throws \Exception
     */
    public function withRefresh($option = self::DELETE_REFRESH['FALSE']): self
    {
        if (in_array($option, self::DELETE_REFRESH)) {
            $this->options['delete_refresh'] = $option;

            return $this;
        }

        throw new \Exception(
            "$option is an invalid conflict option, valid options are: " . explode(', ', self::DELETE_CONFLICT)
        );
    }

    /**
     * Set how to handle conflucts during a delete request
     *
     * @link https://www.elastic.co/guide/en/elasticsearch/reference/7.x/docs-delete-by-query.html#docs-delete-by-query-api-query-params
     *
     * @param string $option
     * @return self
     * @throws \Exception
     */
    public function onConflicts(string $option = self::DELETE_CONFLICT['ABORT']): self
    {
        if (in_array($option, self::DELETE_CONFLICT)) {
            $this->options['delete_conflicts'] = $option;

            return $this;
        }

        throw new \Exception(
            "$option is an invalid conflict option, valid options are: " . explode(', ', self::DELETE_CONFLICT)
        );
    }

    /**
     * Adds a function score of any type
     *
     * @param string $field
     * @param array  $options see elastic search docs for options
     * @param string $boolean
     * @return self
     */
    public function functionScore($functionType, $options = [], $boolean = 'and'): self
    {
        $where = [
            'type'          => 'FunctionScore',
            'function_type' => $functionType,
            'boolean'       => $boolean,
        ];

        $this->wheres[] = array_merge($where, $options);

        return $this;
    }

    /**
     * Get the aggregations returned from query
     *
     * @return array
     */
    public function getAggregationResults(): array
    {
        $this->getResultsOnce();

        return $this->processor->getAggregationResults();
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param array $columns
     * @return \Illuminate\Support\Collection
     */
    public function get($columns = ['*'])
    {
        $original = $this->columns;

        if (is_null($original)) {
            $this->columns = $columns;
        }

        $results = $this->getResultsOnce();

        $this->columns = $original;

        return collect($results);
    }

    /**
     * Get results without re-fetching for subsequent calls.
     *
     * @return array
     */
    protected function getResultsOnce()
    {
        if (!$this->hasProcessedSelect()) {
            $this->results = $this->processor->processSelect($this, $this->runSelect());
        }

        $this->resultsOffset = $this->offset;

        return $this->results;
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return Iterable
     */
    protected function runSelect()
    {
        return $this->connection->select($this->toCompiledQuery());
    }

    /**
     * Get the count of the total records for the paginator.
     *
     * @param array $columns
     * @return int
     */
    public function getCountForPagination($columns = ['*'])
    {
        if ($this->results === null) {
            $this->runPaginationCountQuery();
        }

        $total = $this->processor->getRawResponse()['hits']['total'];

        return is_array($total) ? $total['value'] : $total;
    }

    /**
     * Run a pagination count query.
     *
     * @param array $columns
     * @return array
     */
    protected function runPaginationCountQuery($columns = ['_id'])
    {
        return $this->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->limit(1)
            ->get($columns)->all();
    }

    /**
     * Get the time it took Elasticsearch to perform the query
     *
     * @return int time in milliseconds
     */
    public function getSearchDuration()
    {
        if (!$this->hasProcessedSelect()) {
            $this->getResultsOnce();
        }

        return $this->processor->getRawResponse()['took'];
    }

    /**
     * Get the Elasticsearch representation of the query.
     *
     * @return array
     */
    public function toCompiledQuery(): array
    {
        return $this->toSql();
    }

    /**
     * Get a generator for the given query.
     *
     * @return \Generator
     */
    public function cursor()
    {
        if (is_null($this->columns)) {
            $this->columns = ['*'];
        }

        foreach ($this->connection->cursor($this->toCompiledQuery()) as $document) {
            yield $this->processor->documentFromResult($this, $document);
        }
    }

    /**
     * @inheritdoc
     */
    public function insert(array $values): bool
    {
        // Since every insert gets treated like a batch insert, we will have to detect
        // if the user is inserting a single document or an array of documents.
        $batch = true;

        foreach ($values as $value) {
            // As soon as we find a value that is not an array we assume the user is
            // inserting a single document.
            if (!is_array($value)) {
                $batch = false;
                break;
            }
        }

        if (!$batch) {
            $values = [$values];
        }

        return $this->connection->insert($this->grammar->compileInsert($this, $values));
    }

    /**
     * @inheritdoc
     */
    public function delete($id = null): bool
    {
        // If an ID is passed to the method, we will set the where clause to check the
        // ID to let developers to simply and quickly remove a single row from this
        // database without manually specifying the "where" clauses on the query.
        if (!is_null($id)) {
            $this->where($this->getKeyName(), '=', $id);
        }

        $result = $this->connection->delete($this->grammar->compileDelete($this));

        return !empty($result['deleted']);
    }

    public function __call($method, $parameters)
    {
        if (Str::startsWith($method, 'filterWhere')) {
            return $this->dynamicFilter($method, $parameters);
        }

        return parent::__call($method, $parameters);
    }

    /**
     * @return bool
     */
    protected function hasProcessedSelect(): bool
    {
        if ($this->results === null) {
            return false;
        }

        return $this->offset === $this->resultsOffset;
    }
}
