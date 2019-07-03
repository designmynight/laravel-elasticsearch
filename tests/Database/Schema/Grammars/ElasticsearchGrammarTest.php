<?php

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\Database\Schema\Blueprint;
use DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar;
use Elasticsearch\Client;
use Elasticsearch\Namespaces\CatNamespace;
use Elasticsearch\Namespaces\IndicesNamespace;
use Illuminate\Support\Fluent;
use Mockery as m;
use Tests\TestCase;

class ElasticsearchGrammarTest extends TestCase
{
    /** @var Blueprint|m\CompositeExpectation */
    private $blueprint;

    /** @var Connection|m\CompositeExpectation */
    private $connection;

    /** @var ElasticsearchGrammar */
    private $grammar;

    public function setUp()
    {
        parent::setUp();

        /** @var CatNamespace|m\CompositeExpectation $catNamespace */
        $catNamespace = m::mock(CatNamespace::class);
        $catNamespace->shouldReceive('indices')->andReturn([]);

        /** @var IndicesNamespace|m\CompositeExpectation $indicesNamespace */
        $indicesNamespace = m::mock(IndicesNamespace::class);
        $indicesNamespace->shouldReceive('existsAlias')->andReturnFalse();

        /** @var Client|m\CompositeExpectation $client */
        $client = m::mock(Client::class);
        $client->shouldReceive('cat')->andReturn($catNamespace);
        $client->shouldReceive('indices')->andReturn($indicesNamespace);

        /** @var Connection|m\CompositeExpectation $connection */
        $connection = m::mock(Connection::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $connection->shouldReceive('createConnection')->andReturn($client);

        Carbon::setTestNow(
            Carbon::create(2019, 7, 2, 12)
        );

        $this->blueprint = new Blueprint('indices');
        $this->connection = $connection;
        $this->grammar = new ElasticsearchGrammar();
    }

    /**
     * It returns a closure that will create an index.
     * @test
     * @covers \DesignMyNight\Elasticsearch\Database\Schema\Grammars\ElasticsearchGrammar::compileCreate
     */
    public function it_returns_a_closure_that_will_create_an_index()
    {
        $alias = 'indices_dev';
        $index = '2019_07_02_120000_indices_dev';
        $mapping = [
            'mappings' => [
                'index' => [
                    'properties' => [
                        'title' => [
                            'type' => 'text',
                            'fields' => [
                                'raw' => [
                                    'type' => 'keyword'
                                ]
                            ]
                        ],
                        'date' => [
                            'type' => 'date'
                        ]
                    ]
                ]
            ]
        ];
        $blueprint = clone($this->blueprint);

        $blueprint->text('title')->fields(function (Blueprint $mapping): void {
            $mapping->keyword('raw');
        });
        $blueprint->date('date');

        /** @var IndicesNamespace|m\CompositeExpectation $indicesNamespace */
        $indicesNamespace = m::mock(IndicesNamespace::class);
        $indicesNamespace->shouldReceive('create')->once()->with(['index' => $index, 'body' => $mapping]);
        $indicesNamespace->shouldReceive('existsAlias')->once()->with(['name' => $alias])->andReturnFalse();
        $indicesNamespace->shouldReceive('putAlias')->once()->with(['index' => $index, 'name' => $alias]);

        $this->connection->shouldReceive('indices')->andReturn($indicesNamespace);
        $this->connection->shouldReceive('createAlias')->once()->with($index, $alias)->passthru();

        $this->connection->shouldReceive('createIndex')->once()->with($index, $mapping)->passthru();

        $executable = $this->grammar->compileCreate(new Blueprint(''), new Fluent(), $this->connection);

        $this->assertInstanceOf(Closure::class, $executable);

        $executable($blueprint, $this->connection);
    }
}
