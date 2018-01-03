<?php

namespace DesignMyNight\Elasticsearch;

use DateTime;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use MongoDB\BSON\ObjectID;

class QueryGrammar extends BaseGrammar
{
    /**
     * The index suffix.
     *
     * @var string
     */
    protected $indexSuffix = '';

    public function compileSelect(Builder $builder)
    {
        $query = $this->compileWheres($builder);

        $params = [
            'index' => $builder->from . $this->indexSuffix,
            'body'  => [
                '_source' => $builder->columns && !in_array('*', $builder->columns) ? $builder->columns : true,
                'query'   => $query['query']
            ],
        ];

        if ($query['filter']){
            $params['body']['query']['bool']['filter'] = $query['filter'];
        }

        if ($query['postFilter']){
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

    protected function compileWheres(Builder $builder)
    {
        $queryParts = [
            'query' => 'wheres',
            'filter' => 'filters',
            'postFilter' => 'postFilters'
        ];

        $compiled = [];

        foreach ( $queryParts as $queryPart => $builderVar ){
            $clauses = $builder->$builderVar ?: [];

            $compiled[$queryPart] = $this->compileClauses($builder, $clauses);
        }

        return $compiled;
    }

    protected function compileClauses(Builder $builder, array $clauses)
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

    protected function compileWhereBasic(Builder $builder, $where)
    {
        $value = $this->getValueForWhere($builder, $where);

        $operatorsMap = [
            '>'  => 'gt',
            '>=' => 'gte',
            '<'  => 'lt',
            '<=' => 'lte',
        ];

        if (is_null($value)) {
            $query = [
                'exists' => [
                    'field' => $where['column'],
                ],
            ];
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

        if (($where['operator'] == '!=' && !is_null($value)) || ($where['operator'] == '=' && is_null($value))) {
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

    protected function compileWhereDate($builder, $where)
    {
        if ( $where['operator'] == '=' ){
            $value = $this->getValueForWhere($builder, $where);

            $where['value'] = [$value, $value];

            return $this->compileWhereBetween($builder, $where);
        }
        else {
            return $this->compileWhereBasic($builder, $where);
        }
    }

    protected function compileWhereNested($builder, $where)
    {
        $compiled = $this->compileWheres($where['query']);

        foreach ( $compiled as $queryPart => $clauses ){
            $compiled[$queryPart] = array_map(function($clause) use ($where){
                if ($clause){
                    $this->applyOptionsToClause($clause, $where);
                }

                return $clause;
            }, $clauses);
        }

        $compiled = array_filter($compiled);

        return reset($compiled);
    }

    protected function applyWhereRelationship($builder, $where, $relationship)
    {
        $compiled = $this->compileWheres($where['value']);

        $relationshipFilter = 'has_' . $relationship;

        $query = [
            $relationshipFilter => [
                'type'  => $where['documentType'],
                'query' => $compiled['query'],
            ],
        ];

        $query = $this->applyOptionsToClause($query, $where);

        return $query;
    }

    protected function compileWhereParent($builder, $where)
    {
        return $this->applyWhereRelationship($builder, $where, 'parent');
    }

    protected function compileWhereChild($builder, $where)
    {
        return $this->applyWhereRelationship($builder, $where, 'child');
    }

    protected function compileWhereIn($builder, $where, $not = false)
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

    protected function compileWhereNotIn($builder, $where)
    {
        return $this->compileWhereIn($builder, $where, true);
    }

    protected function compileWhereNull($builder, $where)
    {
        $where['operator'] = '=';

        return $this->compileWhereBasic($builder, $where);
    }

    protected function compileWhereNotNull($builder, $where)
    {
        $where['operator'] = '!=';

        return $this->compileWhereBasic($builder, $where);
    }

    protected function compileWhereBetween($builder, $where)
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

    protected function compileWhereExists($builder, $where, $not = false)
    {
        $query = [
            'exists' => [
                'field' => $where['query']->columns[0],
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

    protected function compileWhereNotExists($builder, $where)
    {
        return $this->compileWhereExists($builder, $where, true);
    }

    protected function compileWhereSearch($builder, $where)
    {
        $fields = '_all';

        if (!empty($where['options']['fields'])) {
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

            $query = array(
                'multi_match' => array(
                    'query'  => $where['value'],
                    'type'   => $type,
                    'fields' => $fields,
                ),
            );
        } else {
            $fields = is_array($fields) ? reset($fields) : $fields;

            $query = array(
                'match' => array(
                    $fields => $where['value'],
                ),
            );
        }

        if (!empty($where['options']['constant_score'])) {
            $query = [
                'constant_score' => [
                    'query' => $query,
                ],
            ];
        }

        return $query;
    }

    protected function compileWhereGeoDistance($builder, $where)
    {
        $query = [
            'geo_distance' => [
                'distance'       => $where['distance'],
                $where['column'] => $where['location'],
            ],
        ];

        return $query;
    }

    protected function compileWhereGeoBoundsIn($builder, $where)
    {
        $query = [
            'geo_bounding_box' => [
                $where['column'] => $where['bounds'],
            ],
        ];

        return $query;
    }

    protected function compileWhereNestedDoc($builder, $where)
    {
        $wheres = $this->compileWheres($where['query']);

        $query = [
            'nested' => [
                'path' => $where['column']
            ],
        ];

        $query['nested'] = array_merge($query['nested'], array_filter($wheres));

        return $query;
    }

    protected function getValueForWhere($builder, $where)
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
        }
        // Convert DateTime values to UTCDateTime.
        else if ($value instanceof ObjectID) {
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

    protected function applyOptionsToClause($clause, $where)
    {
        if (!isset($where['options'])) {
            return $clause;
        }

        $optionsToApply = ['boost'];
        $options        = array_intersect_key($where['options'], array_flip($optionsToApply));

        foreach ($options as $option => $value) {
            $funcName = "apply" . ucfirst($option) . "Option";

            if (method_exists($this, $funcName)){
                $this->$funcName($clause, $value);
            }
        }

        return $clause;
    }

    protected function applyBoostOption($clause, $value)
    {
        $firstKey = key($clause);

        if ($firstKey !== 'term'){
            return $clause[$firstKey]['boost'] = $value;
        }

        $clause['term'] = [
            'type' => [
                'value' => $clause['term']['type'],
                'boost' => $value
            ]
        ];

        return  $clause;
    }

    protected function compileAggregations(Builder $builder)
    {
        $aggregations = [];

        foreach ($builder->aggregations as $aggregation) {
            $result = $this->compileAggregation($builder, $aggregation);

            $aggregations = array_merge($aggregations, $result);
        }

        return $aggregations;
    }

    protected function compileAggregation(Builder $builder, $aggregation)
    {
        $key = $aggregation['key'];

        $method = 'compile' . ucfirst(camel_case($aggregation['type'])) . 'Aggregation';

        $compiled = [
            $key => $this->$method($aggregation)
        ];

        if ( isset($aggregation['aggregations']) ){
            $compiled[$key]['aggregations'] = $this->compileAggregations($aggregation['aggregations']);
        }

        return $compiled;
    }

    protected function compileFilterAggregation($aggregation)
    {
        $compiled = [];

        $filter = $this->compileWheres($aggregation['args']);

        $filters = $filter['filter'] ?? [];

        $query = $filter['query'] ?? [];

        $allFilters = array_merge($query, $filters);

        $compiled = [
            'filter' => $allFilters ?: (object) []
        ];

        return $compiled;
    }

    protected function compileNestedAggregation($aggregation)
    {
        $path = is_array($aggregation['args']) ? $aggregation['args']['path'] : $aggregation['args'];

        return [
            'nested' => [
                'path' => $path
            ]
        ];
    }

    protected function compileTermsAggregation($aggregation)
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'terms' => [
                'field' => $field
            ]
        ];

        if ( is_array($aggregation['args']) && isset($aggregation['args']['size']) ){
            $compiled['terms']['size'] = $aggregation['args']['size'];
        }

        return $compiled;
    }

    protected function compileDateHistogramAggregation($aggregation)
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'date_histogram' => [
                'field' => $field
            ]
        ];

        if ( is_array($aggregation['args']) && isset($aggregation['args']['interval']) ){
            $compiled['date_histogram']['interval'] = $aggregation['args']['interval'];
        }

        return $compiled;
    }

    protected function compileExistsAggregation($aggregation)
    {
        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        $compiled = [
            'exists' => [
                'field' => $field
            ]
        ];

        return $compiled;
    }

    protected function compileReverseNestedAggregation($aggregation)
    {
        return [
            'reverse_nested' => (object) []
        ];
    }

    protected function compileSumAggregation($aggregation)
    {
        return $this->compileMetricAggregation($aggregation);
    }

    protected function compileMetricAggregation($aggregation)
    {
        $metric = $aggregation['type'];

        $field = is_array($aggregation['args']) ? $aggregation['args']['field'] : $aggregation['args'];

        return [
            $metric => [
                'field' => $field
            ]
        ];
    }

    protected function compileChildrenAggregation($aggregation)
    {
        $type = is_array($aggregation['args']) ? $aggregation['args']['type'] : $aggregation['args'];

        return [
            'children' => [
                'type' => $type
            ]
        ];
    }

    protected function compileOrders(Builder $builder, $orders = [])
    {
        $compiledOrders = [];

        foreach ($orders as $order) {
            $column = $order['column'];

            $type = $order['type'] ?? 'basic';

            switch ($type) {
                case 'geoDistance' :
                    $orderSettings = [
                        $column         => $order['options']['coordinates'],
                        'order'         => $order['direction'] < 0 ? 'desc' : 'asc',
                        'unit'          => $order['options']['unit'] ?? 'km',
                        'distance_type' => $order['options']['distanceType'] ?? 'plane',
                    ];

                    $column = '_geo_distance';
                    break;

                default :
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

    public function compileInsert(Builder $builder, array $values)
    {
        $params = [];

        foreach ($values as $doc) {
            if (isset($doc['child_documents'])) {
                foreach ($doc['child_documents']['documents'] as $childDoc) {
                    $params['body'][] = [
                        'index' => [
                            '_index' => $builder->from . $this->indexSuffix,
                            '_type'  => isset($doc['child_documents']['type']) ? $doc['child_documents']['type'] : $builder->type,
                            '_id'    => $childDoc['id'],
                            'parent' => $doc['id'],
                        ],
                    ];

                    $params['body'][] = $childDoc;
                }

                unset($doc['child_documents']);
            }

            $params['body'][] = [
                'index' => [
                    '_index' => $builder->from . $this->indexSuffix,
                    '_type'  => $builder->type,
                    '_id'    => $doc['id'],
                ],
            ];

            $params['body'][] = $doc;
        }

        return $params;
    }

    protected function convertKey($value)
    {
        return (string) $value;
    }

    protected function convertDateTime($value)
    {
        return $value->format('Y-m-d\TH:i:s');
    }

    /**
     * Get the grammar's index suffix.
     *
     * @return string
     */
    public function getIndexSuffix()
    {
        return $this->indexSuffix;
    }

    /**
     * Set the grammar's table suffix.
     *
     * @param  string  $suffix
     * @return $this
     */
    public function setIndexSuffix($suffix)
    {
        $this->indexSuffix = $suffix;

        return $this;
    }
}
