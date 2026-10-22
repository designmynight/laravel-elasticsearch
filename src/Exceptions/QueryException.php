<?php

namespace DesignMyNight\Elasticsearch\Exceptions;

use Illuminate\Database\QueryException as BaseQueryException;
use Throwable;

class QueryException extends BaseQueryException
{
    public function __construct($connectionName, $query, $bindings = [], $previous = null)
    {
        if ($bindings instanceof Throwable) {
            $previous = $bindings;
            $bindings = $query;
            $query = $connectionName;
            $connectionName = 'elasticsearch';
        }

        parent::__construct($connectionName, $query, $bindings, $previous);
    }

    /**
     * Format the error message.
     *
     * @param  string  $connectionName
     * @param  array  $query
     * @param  array  $bindings
     * @param  \Throwable $previous
     * @return string
     */
    protected function formatMessage($connectionName, $query, $bindings, $previous, $maskBindings = false)
    {
        return $previous->getMessage();
    }
}
