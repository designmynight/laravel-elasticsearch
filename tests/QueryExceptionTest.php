<?php

namespace Tests;

use DesignMyNight\Elasticsearch\Exceptions\QueryException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use PHPUnit\Framework\Attributes\Test;

class QueryExceptionTest extends TestCase
{
    #[Test]
    public function testLegacyConstructorPreservesTheQueryAndPreviousException(): void
    {
        $query = ['index' => 'bookings'];
        $bindings = ['booking-1'];
        $previous = new Missing404Exception('document not found');
        $exception = new QueryException($query, $bindings, $previous);

        $this->assertSame('document not found', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($bindings, $exception->getBindings());
        $this->assertSame($query, $exception->getSql());
    }

    #[Test]
    public function testLaravelConstructorPreservesTheQueryAndPreviousException(): void
    {
        $query = ['index' => 'bookings'];
        $bindings = ['booking-1'];
        $previous = new Missing404Exception('document not found');
        $exception = new QueryException('elasticsearch', $query, $bindings, $previous);

        $this->assertSame('document not found', $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
        $this->assertSame($bindings, $exception->getBindings());
        $this->assertSame($query, $exception->getSql());
    }
}
