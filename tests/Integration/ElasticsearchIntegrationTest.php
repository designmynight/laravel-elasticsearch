<?php

namespace Tests\Integration;

use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\Console\Mappings\IndexRemoveCommand;
use DesignMyNight\Elasticsearch\ElasticsearchServiceProvider;
use DesignMyNight\Elasticsearch\EloquentBuilder;
use DesignMyNight\Elasticsearch\Exceptions\BulkInsertQueryException;
use DesignMyNight\Elasticsearch\Exceptions\QueryException;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\Group;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class ElasticsearchIntegrationTest extends TestCase
{
    private $connection;

    private $index;

    public function setUp(): void
    {
        parent::setUp();

        if (! getenv('ES_HOST')) {
            $this->markTestSkipped('ES_HOST is required for Elasticsearch integration tests.');
        }

        $this->index = 'laravel_elasticsearch_test_' . bin2hex(random_bytes(4));
        $this->connection = DB::connection('elasticsearch');
        Schema::connection('sqlite')->create('mappings', function ($table) {
            $table->integer('batch');
            $table->string('mapping');
        });

        $this->connection->createIndex($this->indexName(), [
            'mappings' => [
                'booking' => [
                    'properties' => [
                        'status' => ['type' => 'keyword'],
                        'covers' => ['type' => 'integer'],
                        'created_at' => ['type' => 'date'],
                        'venue_id' => ['type' => 'keyword'],
                        'items' => [
                            'type' => 'nested',
                            'properties' => ['name' => ['type' => 'keyword']],
                        ],
                        'location' => ['type' => 'geo_point'],
                        'amount' => ['type' => 'integer'],
                    ],
                ],
                'charge' => [
                    '_parent' => ['type' => 'booking'],
                    'properties' => [
                        'amount' => ['type' => 'integer'],
                        'status' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ]);
    }

    public function tearDown(): void
    {
        if ($this->connection && $this->index) {
            $this->connection->dropIndex($this->indexName());
        }

        parent::tearDown();
    }

    #[Group('integration')]
    public function testClient68PreservesTypedEs56SearchBulkDeleteAndScrollBehavior(): void
    {
        $documents = [];

        for ($i = 1; $i <= 270; $i++) {
            $documents[] = [
                'id' => 'booking-' . $i,
                'status' => $i % 2 ? 'confirmed' : 'cancelled',
                'covers' => $i % 8,
                'created_at' => '2020-01-01T12:00:00',
                'venue_id' => 'venue-1',
                'items' => [['name' => $i === 1 ? 'cake' : 'tea']],
                'location' => ['lat' => 51.5, 'lon' => -0.12],
                'amount' => 1200,
            ];
        }

        $this->connection->query()->from($this->index)->type('booking')->insert($documents);
        $this->connection->query()
            ->from($this->index)
            ->type('charge')
            ->parentId('booking-1')
            ->insert([['id' => 'charge-1', 'amount' => 1200, 'status' => 'paid']]);
        $this->connection->indices()->refresh(['index' => $this->indexName()]);

        $confirmed = $this->connection->query()
            ->from($this->index)
            ->whereType('booking')
            ->where('status', '=', 'confirmed');

        $this->assertSame(135, $confirmed->getCountForPagination());
        $this->assertCount(10, $confirmed->limit(10)->get());
        $page = $this->connection->query()
            ->from($this->index)
            ->whereType('booking')
            ->whereIn('status', ['confirmed'])
            ->orderBy('covers', 'desc')
            ->paginate(10);
        $this->assertSame(135, $page->total());
        $this->assertSame(7, $page->items()[0]['covers']);
        $this->assertCount(270, $this->connection->query()
            ->from($this->index)
            ->whereDate('created_at', '=', '2020-01-01')
            ->limit(300)
            ->get());
        $this->assertCount(135, $this->connection->query()
            ->from($this->index)
            ->type('booking,charge')
            ->search('confirmed')
            ->limit(200)
            ->get());

        $this->connection->update([
            'index' => $this->indexName(),
            'type' => 'booking',
            'id' => 'booking-1',
            'body' => ['doc' => ['source' => 'raw update']],
        ]);
        $this->assertSame('raw update', $this->connection->get([
            'index' => $this->indexName(),
            'type' => 'booking',
            'id' => 'booking-1',
        ])['_source']['source']);

        $this->connection->delete([
            'index' => $this->indexName(),
            'type' => 'booking',
            'id' => 'booking-2',
        ]);
        $this->connection->indices()->refresh(['index' => $this->indexName()]);

        $nested = $this->connection->query()
            ->from($this->index)
            ->whereNestedDoc('items', function ($query) {
                $query->where('items.name', '=', 'cake');
            })
            ->get();
        $this->assertCount(1, $nested);

        $excluded = $this->connection->query()
            ->from($this->index)
            ->whereType('booking')
            ->whereNot('status', 'cancelled')
            ->limit(200)
            ->get();
        $this->assertCount(135, $excluded);

        $parentHits = $this->connection->query()
            ->from($this->index)
            ->type('charge')
            ->whereParent('booking', function ($query) {
                $query->where('status', '=', 'confirmed');
            })
            ->withInnerHits()
            ->get();
        $this->assertCount(1, $parentHits);
        $this->assertSame('booking-1', $parentHits->first()['_parent']);

        $this->assertCount(270, iterator_to_array(
            $this->connection->query()->from($this->index)->cursor()
        ));
        $this->assertCount(270, iterator_to_array(
            $this->connection->query()->from($this->index)->limit(15)->cursor()
        ));
        $this->assertCount(270, iterator_to_array(
            $this->connection->cursor([
                'index' => $this->indexName(),
                'body' => [
                    'size' => 15,
                    'query' => ['match_all' => (object) []],
                ],
            ])
        ));

        $this->connection->setRequestTimeout(5);
        $this->assertCount(1, $this->connection->query()
            ->from($this->index)
            ->where('_id', '=', 'booking-1')
            ->get());

        $this->connection->delete([
            'index' => $this->indexName(),
            'body' => ['query' => ['term' => ['status' => 'cancelled']]],
        ]);
        $this->connection->indices()->refresh(['index' => $this->indexName()]);
        $this->assertSame(135, $this->connection->query()
            ->from($this->index)
            ->whereType('booking')
            ->getCountForPagination());

        $query = $this->connection->query()
            ->from($this->index)
            ->aggregation('statuses', 'terms', ['field' => 'status'])
            ->aggregation('dates', 'date_histogram', [
                'field' => 'created_at',
                'interval' => 'day',
            ])
            ->aggregation('revenue', 'sum', 'amount')
            ->aggregation('average', 'avg', 'amount')
            ->aggregation('created_ranges', 'date_range', [
                'field' => 'created_at',
                'ranges' => [['from' => '2020-01-01', 'to' => '2021-01-01']],
            ])
            ->aggregation('items', 'nested', 'items', function ($nested) {
                $nested->aggregation('item_names', 'terms', 'items.name')
                    ->aggregation('root', 'reverse_nested');
            })
            ->aggregation('confirmed', 'filter', function ($filtered) {
                $filtered->where('status', '=', 'confirmed');
            })
            ->aggregation('charges', 'children', 'charge');

        $aggregationBuilder = new EloquentBuilder($query);
        $aggregationModel = new SearchableIntegrationModel();
        $aggregationModel->setSearchIndex($this->index);
        $aggregationBuilder->setModel($aggregationModel);
        $aggregationResults = $aggregationBuilder->getAggregations();

        $this->assertArrayHasKey('statuses', $aggregationResults->all());
        $this->assertArrayHasKey('dates', $aggregationResults->all());
        $this->assertArrayHasKey('average', $aggregationResults->all());
        $this->assertArrayHasKey('created_ranges', $aggregationResults->all());
        $this->assertArrayHasKey('items', $aggregationResults->all());
        $this->assertArrayHasKey('charges', $aggregationResults->all());
        $this->assertSame($aggregationResults->all(), $query->getAggregationResults());
    }

    #[Group('integration')]
    public function testClientErrorsAreWrappedOrForwardedAndBulkErrorsArePreserved(): void
    {
        try {
            $this->connection->get([
                'index' => $this->indexName(),
                'type' => 'booking',
                'id' => 'missing-document',
            ]);
            $this->fail('Expected the raw client to throw Missing404Exception.');
        } catch (Missing404Exception $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        try {
            $this->connection->query()
                ->from($this->index)
                ->whereScript('not valid painless syntax')
                ->get();
            $this->fail('Expected invalid query to be wrapped in QueryException.');
        } catch (QueryException $exception) {
            $this->assertInstanceOf(BadRequest400Exception::class, $exception->getPrevious());
        }

        try {
            $this->connection->query()->from($this->index)->type('booking')->insert([
                ['id' => 'bad-document', 'covers' => 'not-an-integer'],
            ]);
            $this->fail('Expected Elasticsearch bulk item errors.');
        } catch (BulkInsertQueryException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }
    }

    #[Group('integration')]
    public function testTypedAliasesMappingsAndRawDeleteByQueryWorkWithTheClient68(): void
    {
        try {
            $this->connection->updateIndex($this->indexName(), [
                'booking' => [
                    'properties' => ['source' => ['type' => 'keyword']],
                ],
            ]);
            $this->fail('Expected a typeless mapping update to be rejected by Elasticsearch.');
        } catch (BadRequest400Exception $exception) {
            $this->assertStringContainsString('mapping type is missing', $exception->getMessage());
        }

        // Client 5.5 rejected this missing type locally; 6.8 sends it to Elasticsearch 5.6.
        $this->connection->indices()->putMapping([
            'index' => $this->indexName(),
            'type' => 'booking',
            'body' => [
                'properties' => ['source' => ['type' => 'keyword']],
            ],
        ]);
        $mapping = $this->connection->indices()->getMapping([
            'index' => $this->indexName(),
            'type' => 'booking',
        ]);
        $this->assertArrayHasKey(
            'source',
            $mapping[$this->indexName()]['mappings']['booking']['properties']
        );

        $alias = $this->index . '_alias';
        $this->connection->createAlias($this->indexName(), $alias);
        $this->assertTrue($this->connection->indices()->existsAlias(['name' => $alias]));

        $this->connection->createAlias($this->indexName(), $alias . '_delete');
        $this->connection->deleteByQuery([
            'index' => $this->indexName(),
            'body' => ['query' => ['match_all' => (object) []]],
        ]);
        $this->connection->indices()->refresh(['index' => $this->indexName()]);
        $this->assertSame(0, $this->connection->query()
            ->from($this->index)
            ->getCountForPagination());
    }

    #[Group('integration')]
    public function testManagerResolvedConnectionCanRunConsecutiveQueries(): void
    {
        $connection = DB::connection('elasticsearch');

        $this->assertSame(Connection::class, get_class($connection));
        $this->assertSame(0, $connection->query()->from($this->index)->getCountForPagination());
        $this->assertSame(0, $connection->query()->from($this->index)->getCountForPagination());
    }

    #[Group('integration')]
    public function testMappingMigrationsCreateTypedIndicesAndApplyTypedUpdates(): void
    {
        $slug = 'mapping_integration_' . bin2hex(random_bytes(3));
        $root = storage_path('framework/testing/' . $slug);
        $mappingDirectory = $root . '/database/mappings';
        File::ensureDirectoryExists($mappingDirectory);
        $this->app->setBasePath($root);

        $first = '2026_01_01_000001_' . $slug;
        $update = '2026_01_02_000001_update_' . $slug;
        File::put($mappingDirectory . '/' . $first . '.json', json_encode([
            'mappings' => [
                'booking' => [
                    'properties' => [
                        'status' => ['type' => 'keyword'],
                        'items' => [
                            'type' => 'nested',
                            'properties' => ['name' => ['type' => 'keyword']],
                        ],
                    ],
                ],
                'charge' => [
                    '_parent' => ['type' => 'booking'],
                    'properties' => ['amount' => ['type' => 'integer']],
                ],
            ],
        ]));

        Artisan::call('migrate:mappings');

        $index = $first . '_dev';
        $alias = $slug . '_dev';
        $this->assertTrue($this->connection->indices()->existsAlias(['name' => $alias]));
        $manualAlias = $slug . '_manual';
        Artisan::call('make:mapping-alias', ['name' => $manualAlias, 'index' => $index]);
        $this->assertTrue($this->connection->indices()->existsAlias(['name' => $manualAlias]));

        File::put($mappingDirectory . '/' . $update . '.json', json_encode([
            'mappings' => [
                'booking' => [
                    'properties' => ['source' => ['type' => 'keyword']],
                ],
            ],
        ]));

        Artisan::call('migrate:mappings');

        $mapping = $this->connection->indices()->getMapping([
            'index' => $index,
            'type' => 'booking',
        ]);
        $this->assertArrayHasKey(
            'source',
            $mapping[$index]['mappings']['booking']['properties']
        );
        Artisan::call('index:list', ['--alias' => $alias]);

        $this->connection->indices()->deleteAlias(['index' => $index, 'name' => $alias]);
        $this->connection->dropIndex($index);
        File::deleteDirectory($root);
    }

    #[Group('integration')]
    public function testIndexCommandsCopySwapRollbackListAndRemoveIndices(): void
    {
        $slug = 'command_integration_' . bin2hex(random_bytes(3));
        $root = storage_path('framework/testing/' . $slug);
        $mappingDirectory = $root . '/database/mappings';
        File::ensureDirectoryExists($mappingDirectory);
        $this->app->setBasePath($root);

        $first = '2026_02_01_000001_' . $slug;
        $second = '2026_02_02_000001_' . $slug;
        $body = [
            'mappings' => [
                'booking' => [
                    'properties' => ['status' => ['type' => 'keyword']],
                ],
            ],
        ];
        File::put($mappingDirectory . '/' . $first . '.json', json_encode($body));
        Artisan::call('migrate:mappings');

        File::put($mappingDirectory . '/' . $second . '.json', json_encode($body));
        Artisan::call('migrate:mappings');

        $firstIndex = $first . '_dev';
        $secondIndex = $second . '_dev';
        $alias = $slug . '_dev';
        $copy = $slug . '_copy';
        $this->connection->query()
            ->from($second)
            ->type('booking')
            ->insert([['id' => 'copy-source', 'status' => 'confirmed']]);
        $this->connection->indices()->refresh(['index' => $secondIndex]);
        $this->connection->createIndex($copy, $body);

        Artisan::call('index:list', ['--alias' => $alias]);
        Artisan::call('index:copy', ['from' => $secondIndex, 'to' => $copy]);
        $this->connection->indices()->refresh(['index' => $copy]);
        $this->assertSame('confirmed', $this->connection->get([
            'index' => $copy,
            'type' => 'booking',
            'id' => 'copy-source',
        ])['_source']['status']);

        Artisan::call('index:swap', [
            'alias' => $alias,
            'index' => $secondIndex,
            'old-index' => $firstIndex,
        ]);
        Artisan::call('index:rollback');
        $aliases = $this->connection->indices()->getAlias(['name' => $alias]);
        $this->assertArrayHasKey($firstIndex, $aliases);

        $this->connection->indices()->deleteAlias(['index' => $firstIndex, 'name' => $alias]);
        $removeCommand = $this->app->make(IndexRemoveCommand::class);
        $removeCommand->setLaravel($this->app);
        $remove = new CommandTester($removeCommand);
        $remove->setInputs(['yes']);
        $remove->execute(['index' => $copy]);
        $this->assertFalse($this->connection->indices()->exists(['index' => $copy]));

        $this->connection->dropIndex($firstIndex);
        $this->connection->dropIndex($secondIndex);
        File::deleteDirectory($root);
    }

    #[Group('integration')]
    public function testSearchableModelsAndCollectionsCanIndexUpdateAndRemoveDocuments(): void
    {
        SearchableIntegrationModel::setConnectionResolver($this->app['db']);

        $model = new SearchableIntegrationModel();
        $model->setSearchIndex($this->index);
        $model->id = 'searchable-1';
        $model->status = 'confirmed';
        $model->covers = 2;
        $this->assertSame('searchable-1', $model->getAttribute('id'));
        $model->setConnection('sqlite');
        $this->assertSame('searchable-1', $model->toSearchableArray()['id']);
        $this->assertSame(
            'elasticsearch',
            $model->onSearchConnection(function ($searchModel) {
                return $searchModel->getConnectionName();
            }, $model)
        );
        $compiled = $model->onSearchConnection(function ($searchModel) {
            return $searchModel->newQueryWithoutScopes()->getQuery()->toCompiledQuery();
        }, $model);
        $this->assertSame($this->indexName(), $compiled['index']);
        $model->addToIndex();
        $this->connection->indices()->refresh(['index' => $this->indexName()]);
        $this->assertSame('confirmed', $this->connection->get([
            'index' => $this->indexName(),
            'type' => 'booking',
            'id' => 'searchable-1',
        ])['_source']['status']);

        $model->status = 'seated';
        $model->updateIndex();

        $this->connection->indices()->refresh(['index' => $this->indexName()]);
        $this->assertSame('seated', $this->connection->get([
            'index' => $this->indexName(),
            'type' => 'booking',
            'id' => 'searchable-1',
        ])['_source']['status']);

        $collection = (new SearchableIntegrationModel())->newCollection([
            tap(new SearchableIntegrationModel(), function ($item) {
                $item->setSearchIndex($this->index);
                $item->id = 'searchable-2';
                $item->status = 'confirmed';
            }),
            tap(new SearchableIntegrationModel(), function ($item) {
                $item->setSearchIndex($this->index);
                $item->id = 'searchable-3';
                $item->status = 'confirmed';
            }),
        ]);
        $collection->addToIndex();

        $this->connection->indices()->refresh(['index' => $this->indexName()]);
        $this->assertSame(3, $this->connection->query()
            ->from($this->index)
            ->whereType('booking')
            ->getCountForPagination());

        $model->removeFromIndex();
        $this->connection->indices()->refresh(['index' => $this->indexName()]);
        $this->assertSame(2, $this->connection->query()
            ->from($this->index)
            ->whereType('booking')
            ->getCountForPagination());

        $child = new SearchableChildIntegrationModel();
        $child->setSearchIndex($this->index);
        $child->id = 'searchable-charge-1';
        $child->amount = 100;
        $child->parent = 'searchable-2';
        $childQuery = $child->newQueryWithoutScopes()->getQuery();
        $this->assertSame('searchable-2', $childQuery->getParentId());
        $child->addToIndex();

        $this->connection->indices()->refresh(['index' => $this->indexName()]);
        $this->assertSame('searchable-2', $this->connection->get([
            'index' => $this->indexName(),
            'type' => 'charge',
            'id' => 'searchable-charge-1',
            'routing' => 'searchable-2',
        ])['_parent']);
    }

    private function indexName(): string
    {
        return $this->index . '_dev';
    }

    protected function getPackageProviders($app)
    {
        return [ElasticsearchServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $host = getenv('ES_HOST') ?: 'localhost';
        $port = getenv('ES_PORT') ?: 9200;

        $app['config']->set('database.connections.elasticsearch', [
            'driver' => 'elasticsearch',
            'host' => $host,
            'hosts' => $host,
            'port' => $port,
            'suffix' => '_dev',
            'options' => [],
        ]);
        $app['config']->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $app['config']->set('database.default', 'sqlite');
    }
}
