<?php

namespace DesignMyNight\Elasticsearch;

trait Searchable {
    public static function getElasticsearchConnectionName(): string
    {
        return 'elasticsearch';
    }

    /**
     * Get the index this model is to be added to
     *
     * @return string
     */
    public function getSearchIndex()
    {
        return $this->searchIndex ?? $this->getTable();
    }

    /**
     * Get the search type associated with the model.
     *
     * @return string
     */
    public function getSearchType()
    {
        return $this->searchType ?? str_singular($this->getTable());
    }

    /**
     * Carry out the given function on the search connection
     *
     * @param  Closure $callback
     * @return mixed
     */
    public function onSearchConnection(\Closure $callback)
    {
        $originalConnection = $this->getConnectionName();

        $this->setConnection(static::getElasticsearchConnectionName());

        $result = $callback(...array_slice(func_get_args(), 1));

        $this->setConnection($originalConnection);

        return $result;
    }

    /**
     * Add to search index
     *
     * @throws Exception
     * @return bool
     */
    public function addToIndex()
    {
        return $this->onSearchConnection(function($model){
            $query = $model->newQueryWithoutScopes();

            return $query->insert($model->toSearchableArray());
        }, $this);
    }

    /**
     * Update indexed document
     *
     * @return bool
     */
    public function updateIndex()
    {
        return $this->addToIndex();
    }

    /**
     * Remove from search index
     *
     * @return bool
     */
    public function removeFromIndex()
    {
        return $this->onSearchConnection(function($model){
            $model->delete();
        }, $this);
    }

    public function toSearchableArray()
    {
        $array = $this->toArray();

        $array['id'] = $this->id;

        unset($array['_id']);

        foreach ( (array) $this->indexAsChildDocuments as $field ){
            $subDocuments = $this->$field ?? [];

            foreach ( $subDocuments as $subDocument ){
                $array['child_documents'][] = $this->getSubDocumentIndexData($subDocument, $field);
            }
        }

        return $array;
    }

    public function getSubDocumentIndexData($document)
    {
        return [
            'type' => $document->getSearchType(),
            'id' => $document->id,
            'document' => $document->toSearchableArray()
        ];
    }

    /**
     * New Collection
     *
     * @param array $models
     * @return DesignMyNight\Elasticsearch\Collection
     */
    public function newCollection(array $models = array())
    {
        return new Collection($models);
    }

    public static function newElasticSearchQuery(): EloquentBuilder
    {
        $model = new static();

        return $model->on(static::getElasticsearchConnectionName())->setModel($model);
    }
}