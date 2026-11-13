<?php

namespace DesignMyNight\Elasticsearch\Exceptions;

use Illuminate\Database\QueryException as BaseQueryException;
use Throwable;

class QueryException extends BaseQueryException
{
     /**
     * Format the error message.
     *
     * @param  array  $query
     * @param  array  $bindings
     * @param  \Throwable $previous
     * @param  bool  $maskBindings
     * @return string
     */
    protected function formatMessage($connectionName, $sql, $bindings, Throwable $previous, $maskBindings = false)
    {
        return $previous->getMessage();
    }
}
