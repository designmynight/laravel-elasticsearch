<?php

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Aggregation;
use DesignMyNight\Elasticsearch\EloquentBuilder;
use DesignMyNight\Elasticsearch\QueryProcessor;
use Tests\Parity\CollinsCompatibleQueryGrammar;
use Tests\Parity\ParityConnectionResolver;
use Tests\Parity\ParityModel;
use Tests\Parity\ResponseConnection;
use Illuminate\Database\Eloquent\Model;

return function () {
    $connection = new ResponseConnection([
        'host' => 'localhost',
        'hosts' => 'localhost:9200',
        'name' => 'elasticsearch',
        'suffix' => '_parity',
        'options' => [],
    ]);

    $grammar = new CollinsCompatibleQueryGrammar();
    $connection->withIndexSuffix($grammar);
    $connection->setQueryGrammar($grammar);

    $build = function ($callback, $index = 'bookings') use ($connection) {
        $query = $connection->query()->from($index);
        $callback($query);

        return $query->toCompiledQuery();
    };

    $queries = [
        'where_operators' => $build(function ($query) {
            $query->where('status', '=', 'confirmed')
                ->where('covers', '!=', 2)
                ->where('covers', '<', 8)
                ->where('covers', '>', 1)
                ->where('covers', '<=', 6)
                ->where('covers', '>=', 2)
                ->where('guest_name', 'like', 'Ada%');
        }),
        'where_in_null_between_date' => $build(function ($query) {
            $query->whereIn('status', ['confirmed', 'seated'])
                ->whereNotIn('status', ['cancelled'])
                ->whereNull('deleted_at')
                ->whereNotNull('guest_name')
                ->whereBetween('covers', [2, 8])
                ->whereBetween('created_at', [
                    Carbon::parse('2020-01-01 00:00:00'),
                    Carbon::parse('2020-01-31 23:59:59'),
                ])
                ->whereDate('created_at', '=', '2020-01-15');
        }),
        'nested_and_or' => $build(function ($query) {
            $query->where(function ($nested) {
                $nested->where('status', '=', 'confirmed')
                    ->orWhere('status', '=', 'seated');
            });
        }),
        'filter_post_filter_search' => $build(function ($query) {
            $query->filterWhereIn('status', ['confirmed', 'seated'])
                ->filterWhere('venue_id', '=', 17, 'and', 'postFilter')
                ->search('birthday dinner', [
                    'fields' => ['guest_name' => 2, 'notes' => 1],
                    'fuzziness' => 'AUTO',
                ]);
        }),
        'nested_relationships_and_types' => $build(function ($query) {
            $query->whereNestedDoc('items', function ($nested) {
                $nested->where('items.name', '=', 'cake');
            })->whereParent('booking', function ($parent) {
                $parent->where('status', '=', 'confirmed');
            }, ['score' => 2])->whereChild('charge', function ($child) {
                $child->where('amount', '>', 0);
            })->whereType('booking,charge')->withInnerHits();
        }),
        'where_not_closure' => $build(function ($query) {
            $query->whereNot(function ($nested) {
                $nested->where('status', '=', 'cancelled');
            });
        }),
        'where_not_closure_or' => $build(function ($query) {
            $query->whereNot(function ($nested) {
                $nested->where('status', '=', 'cancelled');
            }, 'or');
        }),
        'sort_pagination_function_score_geo_script' => $build(function ($query) {
            $query->type('booking,charge')
                ->orderBy('created_at', 'desc', ['missing' => '_last', 'mode' => 'max'])
                ->orderBy('location', 'asc', [
                    'type' => 'geoDistance',
                    'coordinates' => ['lat' => 51.5, 'lon' => -0.12],
                    'unit' => 'km',
                ])
                ->limit(25)
                ->offset(50)
                ->functionScore('field_value_factor', [
                    'field_value_factor' => ['field' => 'popularity'],
                ])
                ->whereGeoDistance('location', ['lat' => 51.5, 'lon' => -0.12], '1km')
                ->whereScript("doc['covers'].value > params.limit", [
                    'params' => ['limit' => 2],
                ]);
        }),
        'aggregations' => $build(function ($query) {
            $query->aggregation('statuses', 'terms', ['field' => 'status', 'size' => 5])
                ->aggregation('dates', 'date_histogram', [
                    'field' => 'created_at',
                    'interval' => 'day',
                    'extended_bounds' => [
                        Carbon::parse('2020-01-01 00:00:00'),
                        Carbon::parse('2020-01-07 23:59:59'),
                    ],
                ])
                ->aggregation('revenue', 'sum', 'amount')
                ->aggregation('average_covers', 'avg', 'covers')
                ->aggregation('date_ranges', 'date_range', [
                    'field' => 'created_at',
                    'ranges' => [['to' => '2020-02-01']],
                ])
                ->aggregation('nested_items', 'nested', 'items', function ($nested) {
                    $nested->aggregation('item_names', 'terms', 'items.name')
                        ->aggregation('root', 'reverse_nested');
                })
                ->aggregation('filtered', 'filter', function ($filtered) {
                    $filtered->where('status', '=', 'confirmed');
                })
                ->aggregation('has_guest_name', 'exists', 'guest_name')
                ->aggregation('charges', 'children', 'charge')
                ->aggregation(new class extends Aggregation {
                    public function __construct()
                    {
                        $this->key = 'class_terms';
                        $this->type = 'terms';
                        $this->arguments = 'status';
                    }
                });
        }),
        'suffix_and_type' => $build(function ($query) {
            $query->type('booking,charge')
                ->where('status', '=', 'confirmed');
        }, 'bookings'),
    ];

    $extendedAggregations = null;
    if (method_exists($connection->getQueryGrammar(), 'compileMinAggregation')) {
        $extendedAggregations = $build(function ($query) {
            $query->aggregation('minimum', 'min', 'covers')
                ->aggregation('maximum', 'max', 'covers')
                ->aggregation('cardinality', 'cardinality', 'venue_id')
                ->aggregation('zoned_dates', 'date_histogram', [
                    'field' => 'created_at',
                    'interval' => 'day',
                    'time_zone' => 'Europe/London',
                    'extended_bounds' => [
                        Carbon::parse('2020-01-01 00:00:00', 'UTC'),
                        Carbon::parse('2020-01-07 23:59:59', 'UTC'),
                    ],
                ]);
        })['body']['aggregations'];
    }

    $singleInsertBuilder = $connection->query()->from('bookings')->type('booking');
    $insertBuilder = $connection->query()->from('bookings')->type('booking');
    $childInsertBuilder = $connection->query()
        ->from('bookings')
        ->type('booking')
        ->routing('venue-4')
        ->parentId('group-9');
    $insertions = [
        'single_document' => $singleInsertBuilder->grammar->compileInsert(
            $singleInsertBuilder,
            [['id' => 'b0', 'status' => 'seated']]
        ),
        'multiple_documents' => $insertBuilder->grammar->compileInsert(
            $insertBuilder,
            [
                ['id' => 'b1', 'status' => 'confirmed'],
                ['id' => 'b2', 'status' => 'seated'],
            ]
        ),
        'child_documents_parent_and_routing' => $childInsertBuilder->grammar->compileInsert($childInsertBuilder, [
                [
                    'id' => 'booking-1',
                    'status' => 'confirmed',
                    'child_documents' => [
                        [
                            'type' => 'charge',
                            'id' => 'charge-1',
                            'document' => ['amount' => 1200],
                        ],
                    ],
                ],
            ]),
    ];

    $simpleDelete = $connection->query()
        ->from('charges')
        ->type('charge')
        ->where('_id', '=', 'charge-1');
    $queryDelete = $connection->query()
        ->from('charges')
        ->where('status', '=', 'refunded');
    $deletes = [
        'simple_id' => $simpleDelete->grammar->compileDelete($simpleDelete),
        'query' => $queryDelete->grammar->compileDelete($queryDelete),
    ];

    $response = [
        'took' => 3,
        'hits' => [
            'total' => 1,
            'hits' => [
                [
                    '_id' => 'booking-1',
                    '_parent' => 'group-9',
                    '_routing' => 'venue-4',
                    '_source' => ['status' => 'confirmed', 'covers' => 2],
                    'inner_hits' => [
                        'charge' => [
                            'hits' => [
                                'hits' => [
                                    ['_id' => 'charge-1', '_source' => ['amount' => 1200]],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'aggregations' => [
            'statuses' => [
                'buckets' => [
                    ['key' => 'confirmed', 'doc_count' => 1],
                ],
            ],
        ],
    ];

    $processorQuery = $connection->query()->from('bookings')->withInnerHits();
    $processor = new QueryProcessor();
    $documents = $processor->processSelect($processorQuery, $response);

    Model::setConnectionResolver(new ParityConnectionResolver($connection));

    $eloquent = new EloquentBuilder($connection->query());
    $eloquent->setModel(new ParityModel());
    $hydrated = $eloquent->hydrate($documents)->map(function ($model) {
        return [
            'attributes' => $model->getAttributes(),
            'key' => $model->getKey(),
        ];
    })->all();

    $aggregationConnection = new ResponseConnection([
        'host' => 'localhost',
        'hosts' => 'localhost:9200',
        'name' => 'elasticsearch',
        'suffix' => '_parity',
        'options' => [],
    ]);
    $aggregationConnection->response = $response;
    $aggregationQuery = $aggregationConnection->query()->from('bookings');
    $aggregationBuilder = new EloquentBuilder($aggregationQuery);
    $aggregationBuilder->setModel(new ParityModel());

    return [
        'queries' => $queries,
        'inserts' => $insertions,
        'deletes' => $deletes,
        'processor' => [
            'documents' => $documents,
            'raw_response' => $processor->getRawResponse(),
            'aggregations' => $processor->getAggregationResults(),
            'hydrated_models' => $hydrated,
            'eloquent_aggregations' => $aggregationBuilder->getAggregations()->all(),
        ],
        'grammar' => [
            'suffix' => $grammar->getIndexSuffix(),
            'date_format' => $grammar->getDateFormat(),
        ],
        'extended_aggregations' => $extendedAggregations,
    ];
};
