<?php

namespace Tests\Parity;

use DesignMyNight\Elasticsearch\Connection;

class ResponseConnection extends Connection
{
    public $response;

    public function select($params, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        return $this->response;
    }
}
