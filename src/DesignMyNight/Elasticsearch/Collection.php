<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Support\Collection as BaseCollection;

class Collection extends BaseCollection
{
    public function addToIndex()
    {
        if ( $this->isEmpty() ){
            return;
        }

        $instance = $this->first();

        $docs = $this->map(function($model){
            return $model->toSearchableArray();
        });

        return $instance->onSearchConnection(function($docs, $instance){
            $query = $instance->newQueryWithoutScopes();

            return $query->insert($docs->all());
        }, $docs, $instance);
    }
}