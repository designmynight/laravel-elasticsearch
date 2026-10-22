<?php

namespace Tests;

use DesignMyNight\Elasticsearch\EloquentBuilder;
use Tests\Parity\ParityConnectionResolver;
use Tests\Parity\ParityModel;
use Tests\Parity\ResponseConnection;

class EloquentBuilderTest extends TestCase
{
    public function testPaginateUsesTheSuppliedTotalInsteadOfTheResponseCount(): void
    {
        $connection = new ResponseConnection([
            'host' => 'localhost',
            'hosts' => 'localhost:9200',
            'name' => 'elasticsearch',
            'suffix' => '_test',
            'options' => [],
        ]);
        $connection->response = [
            'took' => 1,
            'hits' => [
                'total' => 23,
                'hits' => [
                    ['_id' => 'booking-1', '_source' => ['status' => 'confirmed']],
                ],
            ],
        ];
        ParityModel::setConnectionResolver(new ParityConnectionResolver($connection));

        $builder = new EloquentBuilder($connection->query());
        $builder->setModel(new ParityModel());
        $paginator = $builder->paginate(10, ['*'], 'page', 1, function () {
            return 5;
        });

        $this->assertSame(5, $paginator->total());
    }
}
