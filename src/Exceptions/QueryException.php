<?php

namespace DesignMyNight\Elasticsearch\Exceptions;

use Illuminate\Database\QueryException as BaseQueryException;

class QueryException extends BaseQueryException
{
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
