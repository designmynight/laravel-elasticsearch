<?php

namespace DesignMyNight\Elasticsearch;

use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use InvalidArgumentException;
use MongoDB\BSON\ObjectID;

class QueryGrammar extends BaseGrammar
{
    /**
     * The index suffix.
     *
     * @var string
     */
    protected $indexSuffix = '';

    /**
     * Compile a select statement
     *
     * @param  Builder|QueryBuilder  $builder
     * @return array
     */
    public function compileSelect(Builder $builder): array
    {
        $query = $this->compileWheres($builder);

        $params = [
            'index' => $builder->from . $this->indexSuffix,
            'body'  => [
                '_source' => $builder->columns && !in_array('*', $builder->columns) ? $builder->columns : true,
                'query'   => $query['query']
            ],
        ];

        if ($query['filter']) {
            $params['body']['query']['bool']['filter'] = $query['filter'];
        }

        if ($query['postFilter']) {
            $params['body']['post_filter'] = $query['postFilter'];
        }

        if ($builder->aggregations) {
            $params['body']['aggregations'] = $this->compileAggregations($builder);
        }

        // Apply order, offset and limit
        if ($builder->orders) {
            $params['body']['sort'] = $this->compileOrders($builder, $builder->orders);
        }

        if ($builder->offset) {
            $params['body']['from'] = $builder->offset;
        }

        if (isset($builder->limit)) {
            $params['body']['size'] = $builder->limit;
        }

        if (!$params['body']['query']) {
            unset($params['body']['query']);
        }

        // print "<pre>";
        // print str_replace('    ', '  ', json_encode($params, JSON_PRETTY_PRINT));
        // exit;

        return $params;
    }

    /**
     * Compile where clauses for a query
     *
     * @param  Builder  $builder
     * @return array
     */
    protected function compileWheres(Builder $builder): array
    {
        $queryParts = [
            'query' => 'wheres',
            'filter' => 'filters',
            'postFilter' => 'postFilters'
        ];

        $compiled = [];

        foreach ($queryParts as $queryPart => $builderVar) {
            $clauses = $builder->$builderVar ?: [];

            $compiled[$queryPart] = $this->compileClauses($builder, $clauses);
        }

        return $compiled;
    }

    /**
     * Compile general clauses for a query
     *
     * @param  Builder  $builder
     * @param  array  $clauses
     * @return array
     */
    protected function compileClauses(Builder $builder, array $clauses): array
    {
        $query = [];
        $isOr  = false;

        foreach ($clauses as $where) {
            // We use different methods to compile different wheres
            $method = 'compileWhere' . $where['type'];
            $result = $this->{$method}($builder, $where);

            // Wrap the result with a bool to make nested wheres work
            if (count($clauses) > 0 && $where['boolean'] !== 'or') {
                $result = ['bool' => ['must' => [$result]]];
            }

            // If this is an 'or' query then add all previous parts to a 'should'
            if (!$isOr && $where['boolean'] == 'or') {
                $isOr = true;

                if ($query) {
                    $query = ['bool' => ['should' => [$query]]];
                } else {
                    $query['bool']['should'] = [];
                }
            }

            // Add the result to the should clause if this is an Or query
            if ($isOr) {
                $query['bool']['should'][] = $result;
            } else {
                // Merge the compiled where with the others
                $query = array_merge_recursive($query, $result);
            }
        }

        return $query;
    }

    /**
     * Compile a general where clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereBasic(Builder $builder, array $where): array
    {
        $value = $this->getValueForWhere($builder, $where);

        $operatorsMap = [
            '>'  => 'gt',
            '>=' => 'gte',
            '<'  => 'lt',
            '<=' => 'lte',
        ];

        if (is_null($value) || $where['operator'] == 'exists') {
            $query = [
                'exists' => [
                    'field' => $where['column'],
                ],
            ];

            $where['not'] = !$value;
        } else if (in_array($where['operator'], array_keys($operatorsMap))) {
            $operator = $operatorsMap[$where['operator']];
            $query    = [
                'range' => [
                    $where['column'] => [
                        $operator => $value,
                    ],
                ],
            ];
        } else {
            $query = [
                'term' => [
                    $where['column'] => $value,
                ],
            ];
        }

        $query = $this->applyOptionsToClause($query, $where);

        if (!empty($where['not']) || ($where['operator'] == '!=' && !is_null($value)) || ($where['operator'] == '=' && is_null($value))) {
            $query = [
                'bool' => [
                    'must_not' => [
                        $query,
                    ],
                ],
            ];
        }

        return $query;
    }

    /**
     * Compile a date clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereDate(Builder $builder, array $where): array
    {
        if ($where['operator'] == '=') {
            $value = $this->getValueForWhere($builder, $where);

            $where['value'] = [$value, $value];

            return $this->compileWhereBetween($builder, $where);
        }

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * Compile a nested clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereNested(Builder $builder, array $where): array
    {
        $compiled = $this->compileWheres($where['query']);

        foreach ($compiled as $queryPart => $clauses) {
            $compiled[$queryPart] = array_map(function ($clause) use ($where) {
                if ($clause) {
                    $this->applyOptionsToClause($clause, $where);
                }

                return $clause;
            }, $clauses);
        }

        $compiled = array_filter($compiled);

        return reset($compiled);
    }

    /**
     * Compile a relationship clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function applyWhereRelationship(Builder $builder, array $where, string $relationship): array
    {
        $compiled = $this->compileWheres($where['value']);

        $relationshipFilter = "has_{$relationship}";
        $type = $relationship === 'parent' ? 'parent_type' : 'type';

        // pass filter to query if empty allowing a filter interface to be used in relation query
        // otherwise match all in relation query
        if(empty($compiled['query'])) {
            $compiled['query'] = empty($compiled['filter']) ? ['match_all' => (object) []] : $compiled['filter'];
        } else if(!empty($compiled['filter'])) {
            throw new InvalidArgumentException('Cannot use both filter and query contexts within a relation context');
        }

        $query = [
            $relationshipFilter => [
                $type  => $where['documentType'],
                'query' => $compiled['query'],
            ],
        ];

        $query = $this->applyOptionsToClause($query, $where);

        return $query;
    }

    /**
     * Compile a parent clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereParent(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'parent');
    }

    /**
     * @param Builder $builder
     * @param array   $where
     * @return array
     */
    protected function compileWhereParentId(Builder $builder, array $where) {
        return [
            'parent_id' => [
                'type' => $where['relationType'],
                'id'   => $where['id'],
            ],
        ];
    }

    protected function compileWherePrefix(Builder $builder, array $where): array
    {
        $query = [
            'prefix' => [
                $where['column'] => $where['value'],
            ]
        ];

        return $query;
    }

    /**
     * Compile a child clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereChild(Builder $builder, array $where): array
    {
        return $this->applyWhereRelationship($builder, $where, 'child');
    }

    /**
     * Compile an in clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereIn(Builder $builder, array $where, $not = false): array
    {
        $column = $where['column'];
        $values = $this->getValueForWhere($builder, $where);

        $query = [
            'terms' => [
                $column => array_values($values),
            ],
        ];

        $query = $this->applyOptionsToClause($query, $where);

        if ($not) {
            $query = [
                'bool' => [
                    'must_not' => [
                        $query,
                    ],
                ],
            ];
        }

        return $query;
    }

    /**
     * Compile a not in clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereNotIn(Builder $builder, array $where): array
    {
        return $this->compileWhereIn($builder, $where, true);
    }

    /**
     * Compile a null clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereNull(Builder $builder, array $where): array
    {
        $where['operator'] = '=';

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * Compile a not null clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereNotNull(Builder $builder, array $where): array
    {
        $where['operator'] = '!=';

        return $this->compileWhereBasic($builder, $where);
    }

    /**
     * Compile a where between clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @param  bool  $not
     * @return array
     */
    protected function compileWhereBetween(Builder $builder, array $where): array
    {
        $column = $where['column'];
        $values = $this->getValueForWhere($builder, $where);

        if ($where['not']) {
            $query = [
                'bool' => [
                    'should' => [
                        [
                            'range' => [
                                $column => [
                                    'lte' => $values[0],
                                ],
                            ],
                        ],
                        [
                            'range' => [
                                $column => [
                                    'gte' => $values[1],
                                ],
                            ],
                        ],
                    ],
                ],
            ];
        } else {
            $query = [
                'range' => [
                    $column => [
                        'gte' => $values[0],
                        'lte' => $values[1]
                    ],
                ],
            ];
        }

        return $query;
    }

     /**
     * Compile where for function score
     *
     * @param Builder $builder
     * @param array $where
     * @return array
     */
    protected function compileWhereFunctionScore(Builder $builder, array $where): array
    {
        $cleanWhere = $where;

        unset(
            $cleanWhere['function_type'],
            $cleanWhere['type'],
            $cleanWhere['boolean']
        );

        $query = [
            'function_score' => [
                $where['function_type'] => $cleanWhere
            ]
        ];

        return $query;
    }

    /**
     * Compile a search clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereSearch(Builder $builder, array $where): array
    {
        $fields = '_all';

        if (! empty($where['options']['fields'])) {
            $fields = $where['options']['fields'];
        }

        if (is_array($fields) && !is_numeric(array_keys($fields)[0])) {
            $fieldsWithBoosts = [];

            foreach ($fields as $field => $boost) {
                $fieldsWithBoosts[] = "{$field}^{$boost}";
            }

            $fields = $fieldsWithBoosts;
        }

        if (is_array($fields) && count($fields) > 1) {
            $type = isset($where['options']['matchType']) ? $where['options']['matchType'] : 'most_fields';

            $query = [
                'multi_match' => [
                    'query'  => $where['value'],
                    'type'   => $type,
                    'fields' => $fields,
                ],
            ];
        } else {
            $field = is_array($fields) ? reset($fields) : $fields;

            $query = [
                'match' => [
                    $field => [
                        'query' => $where['value'],
                    ]
                ],
            ];
        }

        if (! empty($where['options']['fuzziness'])) {
            $matchType = array_keys($query)[0];

            if ($matchType === 'multi_match') {
                $query[$matchType]['fuzziness'] = $where['options']['fuzziness'];
            } else {
                $query[$matchType][$field]['fuzziness'] = $where['options']['fuzziness'];
            }
        }

        if (! empty($where['options']['constant_score'])) {
            $query = [
                'constant_score' => [
                    'query' => $query,
                ],
            ];
        }

        return $query;
    }

    /**
     * Compile a script clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereScript(Builder $builder, array $where): array
    {
        return [
            'script' => [
                'script' => array_merge($where['options'], ['source' => $where['script']]),
            ],
        ];
    }

    /**
     * Compile a geo distance clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereGeoDistance($builder, $where): array
    {
        $query = [
            'geo_distance' => [
                'distance'       => $where['distance'],
                $where['column'] => $where['location'],
            ],
        ];

        return $query;
    }

    /**
     * Compile a where geo bounds clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereGeoBoundsIn(Builder $builder, array $where): array
    {
        $query = [
            'geo_bounding_box' => [
                $where['column'] => $where['bounds'],
            ],
        ];

        return $query;
    }

    /**
     * Compile a where nested doc clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereNestedDoc(Builder $builder, $where): array
    {
        $wheres = $this->compileWheres($where['query']);

        $query = [
            'nested' => [
                'path' => $where['column']
            ],
        ];

        $query['nested'] = array_merge($query['nested'], array_filter($wheres));

        if (isset($where['operator']) && $where['operator'] === '!=') {
            $query = [
                'bool' => [
                    'must_not' => [
                        $query
                    ]
                ]
            ];
        }

        return $query;
    }

    /**
     * Compile a where not clause
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return array
     */
    protected function compileWhereNot(Builder $builder, $where): array
    {
        return [
            'bool' => [
                'must_not' => [
                    $this->compileWheres($where['query'])['query']
                ]
            ]
        ];
    }

    /**
     * Get value for the where
     *
     * @param  Builder  $builder
     * @param  array  $where
     * @return mixed
     */
    protected function getValueForWhere(Builder $builder, array $where)
    {
        switch ($where['type']) {
            case 'In':
            case 'NotIn':
            case 'Between':
                $value = $where['values'];
                break;

            case 'Null':
            case 'NotNull':
                $value = null;
                break;

            default:
                $value = $where['value'];
        }

        // Convert DateTime values to UTCDateTime.
        if ($value instanceof DateTime) {
            $value = $this->convertDateTime($value);
        } else if ($value instanceof ObjectID) {
            // Convert DateTime values to UTCDateTime.
            $value = $this->convertKey($value);
        } else if (is_array($value)) {
            foreach ($value as &$val) {
                if ($val instanceof DateTime) {
                    $val = $this->convertDateTime($val);
                } else if ($val instanceof ObjectID) {
                    $val = $this->convertKey($val);
                }
            }
        }

        return $value;
    }

    /**
     * Apply the given options from a where to a query clause
     *
     * @param  array  $clause
     * @param  array  $where
     * @return array
     */
    protected function applyOptionsToClause(array $clause, array $where)
    {
        if (!isset($where['options'])) {
            return $clause;
        }

        $optionsToApply = ['boost', 'inner_hits'];
        $options        = array_intersect_key($where['options'], array_flip($optionsToApply));

        foreach ($options as $option => $value) {
            $method = 'apply' . studly_case($option) . 'Option';

            if (method_exists($this, $method)) {
                $clause = $this->$method($clause, $value, $where);
            }
        }

        return $clause;
    }

    /**
     * Apply a boost option to the clause
     *
     * @param  array  $clause
     * @param  mixed  $value
     * @param  array  $where
     * @return array
     */
    protected function applyBoostOption(array $clause, $value, $where): array
    {
        $firstKey = key($clause);

        if ($firstKey !== 'term') {
            return $clause[$firstKey]['boost'] = $value;
        }

        $key = key($clause['term']);

        $clause['term'] = [
            $key => [
                'value' => $clause['term'][$key],
                'boost' => $value
            ]
        ];

        return  $clause;
    }

    /**
     * Apply inner hits options to the clause
     *
     * @param  array $clause
     * @param  mixed  $value
     * @param  array  $where
     * @return array
     */
    protected function applyInnerHitsOption(array $clause, $value, $where): array
    {
        $firstKey = key($clause);

        $clause[$firstKey]['inner_hits'] = empty($value) || $value === true ? (object) [] : (array) $value;

        return $clause;
    }

    /**
     * Compile all aggregations
     *
     * @param  Builder  $builder
     * @return array
     */
    protected function compileAggregations(Builder $builder): array
    {
        $aggregations = [];

        foreach ($builder->aggregations as $aggregation) {
            $result = $this->compileAggregation($builder, $aggregation);

            $aggregations = array_merge($aggregations, $result);
        }

        return $aggregations;
    }

    /**
     * Compile a single aggregation
     *
     * @param  Builder  $builder
     * @param  array  $aggregation
     * @return array
     */
    protected function compileAggregation(Builder $builder, array $aggregation): array
    {
        $key = $aggregation['key'];

        $method = 'compile' . ucfirst(camel_case($aggregation['type'])) . 'Aggregation';

        $compiled = [
            $key => $this->$method($aggregation)
        ];

        if (isset($aggregation['aggregations']) && $aggregation['aggregations']->aggregations) {
            $compiled[$key]['aggregations'] = $this->compileAggregations($aggregation['aggregations']);
        }

        return $compiled;
    }

    /**
     * Compile filter aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileFilterAggregation(array $aggregation): array
    {
        $filter = $this->compileWheres($aggregation['args']);

        $filters = $filter['filter'] ?? [];
        $query = $filter['query'] ?? [];

        $allFilters = array_merge($query, $filters);

        return [
            'filter' => $allFilters ?: ['match_all' => (object) []]
        ];
    }

    /**
     * Compile nested aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileNestedAggregation(array $aggregation): array
    {
        $path = is_array($aggregation['args']) ? $aggregation['args']['path'] : $aggregation['args'];

        return [
            'nested' => [
                'path' => $path
            ]
        ];
    }

    /**
     * Compile terms aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileTermsAggregation(array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'terms' => [
                'field' => $field
            ]
        ];

        $allowedArgs = [
            'collect_mode',
            'exclude',
            'execution_hint',
            'include',
            'min_doc_count',
            'missing',
            'order',
            'script',
            'show_term_doc_count_error',
            'size',
        ];

        if (is_array($aggregation['args'])) {
            $validArgs = array_intersect_key($aggregation['args'], array_flip($allowedArgs));
            $compiled['terms'] = array_merge($compiled['terms'], $validArgs);
        }

        return $compiled;
    }

    /**
     * Compile date histogram aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileDateHistogramAggregation(array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'date_histogram' => [
                'field' => $field
            ]
        ];

        if (is_array($aggregation['args'])) {
            if (isset($aggregation['args']['interval'])) {
                $compiled['date_histogram']['interval'] = $aggregation['args']['interval'];
            }

            if (isset($aggregation['args']['min_doc_count'])) {
                $compiled['date_histogram']['min_doc_count'] = $aggregation['args']['min_doc_count'];
            }

            if (isset($aggregation['args']['extended_bounds']) && is_array($aggregation['args']['extended_bounds'])) {
                $compiled['date_histogram']['extended_bounds'] = [];
                $compiled['date_histogram']['extended_bounds']['min'] = $this->convertDateTime($aggregation['args']['extended_bounds'][0]);
                $compiled['date_histogram']['extended_bounds']['max'] = $this->convertDateTime($aggregation['args']['extended_bounds'][1]);
            }
        }

        return $compiled;
    }

    /**
     * Compile date range aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileDateRangeAggregation(array $aggregation): array
    {
        $compiled = [
            'date_range' => $aggregation['args']
        ];

        return $compiled;
    }

    /**
     * Compile exists aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileExistsAggregation(array $aggregation): array
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'exists' => [
                'field' => $field
            ]
        ];

        return $compiled;
    }

    /**
     * Compile reverse nested aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileReverseNestedAggregation(array $aggregation): array
    {
        return [
            'reverse_nested' => (object) []
        ];
    }

    /**
     * Compile sum aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileSumAggregation(array $aggregation): array
    {
        return $this->compileMetricAggregation($aggregation);
    }

    /**
     * Compile avg aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileAvgAggregation(array $aggregation): array
    {
        return $this->compileMetricAggregation($aggregation);
    }

    /**
     * Compile metric aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileMetricAggregation(array $aggregation): array
    {
        $metric = $aggregation['type'];

        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        return [
            $metric => [
                'field' => $field
            ]
        ];
    }

    /**
     * Compile children aggregation
     *
     * @param  array  $aggregation
     * @return array
     */
    protected function compileChildrenAggregation(array $aggregation): array
    {
        $type = is_array($aggregation['args']) ? $aggregation['args']['type'] : $aggregation['args'];

        return [
            'children' => [
                'type' => $type
            ]
        ];
    }

    /**
     * Compile the orders section of a query
     *
     * @param  Builder  $builder
     * @param  array  $orders
     * @return array
     */
    protected function compileOrders(Builder $builder, $orders = []): array
    {
        $compiledOrders = [];

        foreach ($orders as $order) {
            $column = $order['column'];

            $type = $order['type'] ?? 'basic';

            switch ($type) {
                case 'geoDistance':
                    $orderSettings = [
                        $column         => $order['options']['coordinates'],
                        'order'         => $order['direction'] < 0 ? 'desc' : 'asc',
                        'unit'          => $order['options']['unit'] ?? 'km',
                        'distance_type' => $order['options']['distanceType'] ?? 'plane',
                    ];

                    $column = '_geo_distance';
                    break;

                default:
                    $orderSettings = [
                        'order' => $order['direction'] < 0 ? 'desc' : 'asc'
                    ];

                    $allowedOptions = ['missing', 'mode'];

                    $options = isset($order['options']) ? array_intersect_key($order['options'], array_flip($allowedOptions)) : [];

                    $orderSettings = array_merge($options, $orderSettings);
            }

            $compiledOrders[] = [
                $column => $orderSettings,
            ];
        }

        return $compiledOrders;
    }

    /**
     * Compile the given values to an Elasticsearch insert statement
     *
     * @param  Builder|QueryBuilder  $builder
     * @param  array  $values
     * @return array
     */
    public function compileInsert(Builder $builder, array $values): array
    {
        $params = [];

        foreach ($values as $doc) {
            if (isset($doc['child_documents'])) {
                foreach ($doc['child_documents'] as $childDoc) {
                    $params['body'][] = [
                        'index' => [
                            '_index' => $builder->from . $this->indexSuffix,
                            '_id'    => $childDoc['id'],
                            'parent' => $doc['id'],
                        ]
                    ];

                    $params['body'][] = $childDoc['document'];
                }

                unset($doc['child_documents']);
            }

            $index = [
                '_index' => $builder->from . $this->indexSuffix,
                '_id'    => $doc['id'],
            ];

            if(isset($doc['_routing'])) {
                $index['routing'] = $doc['_routing'];
                unset($doc['_routing']);
            }
            else if($routing = $builder->getRouting()) {
                $index['routing'] = $routing;
            }

            if ($parentId = $builder->getParentId()) {
                $index['parent'] = $parentId;
            } else if (isset($doc['_parent'])) {
                $index['parent'] = $doc['_parent'];
                unset($doc['_parent']);
            }

            $params['body'][] = ['index' => $index];

            unset($doc['id']);

            $params['body'][] = $doc;
        }

        return $params;
    }

    /**
     * Compile a delete query
     *
     * @param  Builder|QueryBuilder  $builder
     * @return array
     */
    public function compileDelete(Builder $builder): array
    {
        $params = [
            'index' => $builder->from . $this->indexSuffix,
            'type'  => $builder->type,
            'id'    => (string) $builder->wheres[0]['value']
        ];

        if ($routing = $builder->getRouting()) {
            $params['routing'] = $routing;
        }

        if ($parentId = $builder->getParentId()) {
            $params['parent'] = $parentId;
        }

        return $params;
    }

    /**
     * Convert a key to an Elasticsearch-friendly format
     *
     * @param  mixed  $value
     * @return string
     */
    protected function convertKey($value): string
    {
        return (string) $value;
    }

    /**
     * Compile a delete query
     *
     * @param  Builder  $builder
     * @return string
     */
    protected function convertDateTime($value): string
    {
        if (is_string($value)) {
            return $value;
        }

        return $value->format($this->getDateFormat());
    }

    /**
     * @inheritdoc
     */
    public function getDateFormat():string
    {
        return 'Y-m-d\TH:i:s';
    }

    /**
     * Get the grammar's index suffix.
     *
     * @return string
     */
    public function getIndexSuffix(): string
    {
        return $this->indexSuffix;
    }

    /**
     * Set the grammar's table suffix.
     *
     * @param  string  $suffix
     * @return $this
     */
    public function setIndexSuffix(string $suffix): self
    {
        $this->indexSuffix = $suffix;

        return $this;
    }
}
