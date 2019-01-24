<?php

namespace DesignMyNight\Elasticsearch;

trait Searchable
{
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
        $arguments = array_slice(func_get_args(), 1);

        $elasticModel = clone $arguments[0];
        $elasticModel->setConnection(static::getElasticsearchConnectionName());

        $arguments[0] = $elasticModel;

        return $callback(...$arguments);
    }

    /**
     * Implementing models can override this method to set additional query
     * parameters to be used when searching
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function setKeysForSearch($query)
    {
        return $query;
    }

    /**
     * Add to search index
     *
     * @throws Exception
     * @return bool
     */
    public function addToIndex()
    {
        return $this->onSearchConnection(function ($model) {
            $query = $model->setKeysForSaveQuery($model->newQueryWithoutScopes());

            $model->setKeysForSearch($query);

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
        return $this->onSearchConnection(function ($model) {
            $query = $model->setKeysForSaveQuery($model->newQueryWithoutScopes());

            $model->setKeysForSearch($query);

            return $query->delete();
        }, $this);
    }

    /**
     * Create a searchable version of this model
     *
     * @return array
     */
    public function toSearchableArray()
    {
        $array = $this->toArray();

        foreach ($this->getArrayableRelations() as $key => $relation) {
            $attributeName = snake_case($key);

            if (isset($array[$attributeName]) && method_exists($relation, 'toSearchableArray')) {
                $array[$attributeName] = $relation->toSearchableArray($array[$attributeName]);
            } elseif (isset($array[$attributeName]) && $relation instanceof \Illuminate\Support\Collection) {
                $array[$attributeName] = $relation->map(function ($item, $i) use ($array, $attributeName) {
                    if (method_exists($item, 'toSearchableArray')) {
                        return $item->toSearchableArray($array[$attributeName][$i]);
                    }

                    return $item;
                })->all();
            }
        }

        $array['id'] = $this->id;

        unset($array['_id']);

        foreach ((array) $this->indexAsChildDocuments as $field) {
            $subDocuments = $this->$field ?? [];

            foreach ($subDocuments as $subDocument) {
                $array['child_documents'][] = $this->getSubDocumentIndexData($subDocument, $field);
            }
        }

        return $array;
    }

    /**
     * Build index details for a sub document
     *
     * @param  \Illuminate\Database\Eloquent\Model $document
     * @return array
     */
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
     * @return Collection
     */
    public function newCollection(array $models = [])
    {
        return new Collection($models);
    }

    public static function newElasticsearchQuery(): EloquentBuilder
    {
        $model = new static();

        return $model
            ->on(static::getElasticsearchConnectionName())
            ->whereType($model->getSearchType());
    }
}
