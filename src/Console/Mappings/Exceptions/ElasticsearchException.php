<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings\Exceptions;

/**
 * Class ElasticsearchException
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings\Exceptions
 */
abstract class ElasticsearchException extends \Exception
{

    /**
     * ElasticsearchException constructor.
     *
     * @param array $error
     */
    public function __construct(array $error)
    {
        parent::__construct($error['error']['reason'], $error['status']);
    }
}
