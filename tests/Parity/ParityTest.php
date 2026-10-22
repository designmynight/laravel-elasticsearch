<?php

namespace Tests\Parity;

use Tests\TestCase;

class ParityTest extends TestCase
{
    public function testCompiledQueriesAndResponsesMatchTheV4314Fixtures(): void
    {
        $cases = require __DIR__ . '/cases.php';
        $outputs = $cases();
        $extendedAggregations = $outputs['extended_aggregations'];
        unset($outputs['extended_aggregations']);

        $expected = file_get_contents(__DIR__ . '/fixtures/v4314.json');
        $actual = json_encode($outputs, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        $this->assertSame($expected, $actual);
        $this->assertNotNull($extendedAggregations);
        $this->assertArrayHasKey('min', $extendedAggregations['minimum']);
        $this->assertArrayHasKey('max', $extendedAggregations['maximum']);
        $this->assertArrayHasKey('cardinality', $extendedAggregations['cardinality']);
        $this->assertSame(
            'Europe/London',
            $extendedAggregations['zoned_dates']['date_histogram']['time_zone']
        );
    }
}
