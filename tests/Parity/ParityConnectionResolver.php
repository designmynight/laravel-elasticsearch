<?php

namespace Tests\Parity;

use DesignMyNight\Elasticsearch\Connection;
use Illuminate\Database\ConnectionResolverInterface;

class ParityConnectionResolver implements ConnectionResolverInterface
{
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function connection($name = null)
    {
        return $this->connection;
    }

    public function getDefaultConnection()
    {
        return 'elasticsearch';
    }

    public function setDefaultConnection($name)
    {
    }
}
