<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Database\Eloquent\Collection as BaseCollection;

class Collection extends BaseCollection
{
    public function addToIndex()
    {
        if ($this->isEmpty()) {
            return;
        }

        $instance = $this->first();

        $docs = $this->map(function ($model) {
            return $model->toSearchableArray();
        });

        return $instance->onSearchConnection(function ($instance) use ($docs) {
            $query = $instance->newQueryWithoutScopes();

            return $query->insert($docs->all());
        }, $instance);
    }
}
