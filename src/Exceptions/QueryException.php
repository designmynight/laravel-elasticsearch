<?php

namespace DesignMyNight\Elasticsearch\Exceptions;

use Illuminate\Database\QueryException as BaseQueryException;

class QueryException extends BaseQueryException
{
     /**
     * Format the error message.
     *
     * @param  array  $query
     * @param  array  $bindings
     * @param  \Exception $previous
     * @return string
     */
    protected function formatMessage($query, $bindings, $previous)
    {
        return $previous->getMessage();
    }
}
