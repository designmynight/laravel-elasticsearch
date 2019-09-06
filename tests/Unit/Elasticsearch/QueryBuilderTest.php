<?php

namespace Tests\Unit\Elasticsearch;

use DesignMyNight\Elasticsearch\Connection;
use DesignMyNight\Elasticsearch\QueryBuilder;
use DesignMyNight\Elasticsearch\QueryGrammar;
use DesignMyNight\Elasticsearch\QueryProcessor;
use Mockery as m;
use Tests\TestCase;

class QueryBuilderTest extends TestCase
{
    /** @var QueryBuilder */
    private $builder;

    public function setUp()
    {
        parent::setUp();

        /** @var Connection|m\MockInterface $connection */
        $connection = m::mock(Connection::class);

        /** @var QueryGrammar|m\MockInterface $queryGrammar */
        $queryGrammar = m::mock( QueryGrammar::class );

        /** @var QueryProcessor|m\MockInterface $queryProcessor */
        $queryProcessor = m::mock( QueryProcessor::class );

        $this->builder = new QueryBuilder($connection, $queryGrammar, $queryProcessor);
    }

    /**
     * @test
     * @dataProvider whereParentIdProvider
     */
    public function adds_parent_id_to_wheres_clause(string $name, $id, string $boolean):void
    {
        $this->builder->whereParentId($name, $id, $boolean);

        $this->assertEquals([
            'type' => 'ParentId',
            'name' => $name,
            'id' => $id,
            'boolean' => $boolean,
        ], $this->builder->wheres[0]);
    }

    /**
     * @return array
     */
    public function whereParentIdProvider():array
    {
        return [
            'boolean and' => ['my_parent', 1, 'and'],
            'boolean or' => ['my_parent', 1, 'or'],
        ];
    }
}
