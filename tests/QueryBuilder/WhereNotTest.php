<?php

namespace Tests\QueryBuilder;

use DesignMyNight\Elasticsearch\Connection;
use Tests\TestCase;

class WhereNotTest extends TestCase
{
    private function makeQuery()
    {
        return (new Connection([
            'host' => 'localhost',
            'hosts' => 'localhost:9200',
            'name' => 'elasticsearch',
            'suffix' => '_test',
            'options' => [],
        ]))->query()->from('bookings');
    }

    public function testScalarWhereNotSupportsLaravelTwoArgumentShorthand(): void
    {
        $query = $this->makeQuery()->whereNot('status', 'cancelled');

        $compiled = $query->toCompiledQuery()['body']['query'];

        $this->assertSame(
            'cancelled',
            $compiled['bool']['must'][0]['bool']['must_not'][0]['bool']['must'][0]['term']['status']
        );
    }

    public function testScalarWhereNotAcceptsOperatorValueAndBoolean(): void
    {
        $query = $this->makeQuery()->whereNot('covers', '>', 4, 'or');

        $compiled = $query->toCompiledQuery()['body']['query'];

        $this->assertSame('or', $query->wheres[0]['boolean']);
        $this->assertSame(
            4,
            $compiled['bool']['should'][0]['bool']['must_not'][0]['bool']['must'][0]['range']['covers']['gt']
        );
    }

    public function testArrayWhereNotUsesLaravelWhereArraySemantics(): void
    {
        $query = $this->makeQuery()->whereNot(['status' => 'cancelled', 'covers' => 2]);

        $compiled = $query->toCompiledQuery()['body']['query'];

        $this->assertSame(
            'cancelled',
            $compiled['bool']['must'][0]['bool']['must_not'][0]['bool']['must'][0]['bool']['must'][0]['term']['status']
        );
        $this->assertSame(
            2,
            $compiled['bool']['must'][0]['bool']['must_not'][0]['bool']['must'][0]['bool']['must'][1]['term']['covers']
        );
    }
}
