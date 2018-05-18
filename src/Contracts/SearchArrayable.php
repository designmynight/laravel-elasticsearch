<?php

namespace DesignMyNight\Elasticsearch\Contracts;

/**
 * Interface SearchArrayable
 *
 * @package DesignMyNight\Elasticsearch\Contracts
 */
interface SearchArrayable
{
    /**
     * @return array
     */
    public function toSearchableArray():array;
}