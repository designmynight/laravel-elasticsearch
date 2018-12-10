<?php

namespace DesignMyNight\Elasticsearch\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * Interface FilterInterface
 * @package DesignMyNight\Elasticsearch\Contracts
 */
interface FilterInterface
{
    /**
     * @param Builder $builder
     *
     * @return Builder
     */
    public function apply(Builder $builder):Builder;
}
