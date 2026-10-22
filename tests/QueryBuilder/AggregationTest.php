<?php

namespace Tests\QueryBuilder;

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Connection;
use Tests\TestCase;

class AggregationTest extends TestCase
{
    public function testMetricAggregationsAndDateHistogramCompile(): void
    {
        $connection = new Connection([
            'host' => 'localhost',
            'hosts' => 'localhost:9200',
            'name' => 'elasticsearch',
            'suffix' => '_test',
            'options' => [],
        ]);

        $query = $connection->query()
            ->from('bookings')
            ->aggregation('total', 'sum', 'covers')
            ->aggregation('average', 'avg', 'covers')
            ->aggregation('created', 'date_histogram', [
                'field' => 'created_at',
                'interval' => 'day',
                'extended_bounds' => [
                    Carbon::parse('2020-01-01 00:00:00', 'UTC'),
                    Carbon::parse('2020-01-03 00:00:00', 'UTC'),
                ],
            ]);

        $aggregations = $query->toCompiledQuery()['body']['aggregations'];

        $this->assertSame(['sum' => ['field' => 'covers']], $aggregations['total']);
        $this->assertSame(['avg' => ['field' => 'covers']], $aggregations['average']);
        $this->assertSame(
            ['min' => '2020-01-01T00:00:00', 'max' => '2020-01-03T00:00:00'],
            $aggregations['created']['date_histogram']['extended_bounds']
        );
    }
}
