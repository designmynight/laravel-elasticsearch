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

        return $instance->onSearchConnection(function ($instance) {
            $docs = $this->map(function ($model) {
                return $model->onSearchConnection(function ($model) {
                    return $model->toSearchableArray();
                }, $model);
            });

            $query = $instance->newQueryWithoutScopes();

            return $query->insert($docs->all());
        }, $instance);
    }
}
