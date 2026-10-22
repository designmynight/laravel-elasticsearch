<?php

namespace Tests\Integration;

class SearchableChildIntegrationModel extends SearchableIntegrationModel
{
    protected $table = 'charges';

    protected $searchType = 'charge';

    protected function newBaseQueryBuilder()
    {
        $query = $this->getConnection()->query();

        if (isset($this->parent)) {
            $query->parentId($this->parent);
        }

        return $query;
    }
}
