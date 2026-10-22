<?php

namespace Tests\Parity;

use DesignMyNight\Elasticsearch\QueryGrammar;
use Illuminate\Database\Query\Builder;

class CollinsCompatibleQueryGrammar extends QueryGrammar
{
    protected function getValueForWhere(Builder $builder, array $where)
    {
        return parent::getValueForWhere($builder, $where);
    }

    protected function applyWhereRelationship(Builder $builder, array $where, string $relationship): array
    {
        return parent::applyWhereRelationship($builder, $where, $relationship);
    }

    protected function convertDateTime($value): string
    {
        return parent::convertDateTime($value);
    }

    protected function compileDateHistogramAggregation(array $aggregation): array
    {
        return parent::compileDateHistogramAggregation($aggregation);
    }

    public function getIndexSuffix(): string
    {
        return parent::getIndexSuffix();
    }

    public function setIndexSuffix(string $suffix): self
    {
        return parent::setIndexSuffix($suffix);
    }

    public function getDateFormat(): string
    {
        return parent::getDateFormat();
    }
}
