<?php

namespace Tests\QueryBuilder;

use Carbon\Carbon;
use DesignMyNight\Elasticsearch\Connection;
use Tests\TestCase;

class AggregationTest extends TestCase
{
    public function testMetricAggregationsAndDateHistogramTimeZoneCompile(): void
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
            ->aggregation('minimum', 'min', 'covers')
            ->aggregation('maximum', 'max', 'covers')
            ->aggregation('cardinality', 'cardinality', 'venue_id')
            ->aggregation('created', 'date_histogram', [
                'field' => 'created_at',
                'interval' => 'day',
                'time_zone' => 'Europe/London',
                'extended_bounds' => [
                    Carbon::parse('2020-01-01 00:00:00', 'UTC'),
                    Carbon::parse('2020-01-03 00:00:00', 'UTC'),
                ],
            ]);

        $aggregations = $query->toCompiledQuery()['body']['aggregations'];

        $this->assertSame(['min' => ['field' => 'covers']], $aggregations['minimum']);
        $this->assertSame(['max' => ['field' => 'covers']], $aggregations['maximum']);
        $this->assertSame(['cardinality' => ['field' => 'venue_id']], $aggregations['cardinality']);
        $this->assertSame('Europe/London', $aggregations['created']['date_histogram']['time_zone']);
        $this->assertSame(
            ['min' => '2020-01-01T00:00:00', 'max' => '2020-01-03T00:00:00'],
            $aggregations['created']['date_histogram']['extended_bounds']
        );
    }
}
