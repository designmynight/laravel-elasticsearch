<?php

namespace DesignMyNight\Elasticsearch;

use DesignMyNight\Elasticsearch\Contracts\FilterInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
        $originalConnection = $this->getConnectionName();

        $this->setConnection(static::getElasticsearchConnectionName());

        try {
            $result = $callback(...array_slice(func_get_args(), 1));
        } finally {
            $this->setConnection($originalConnection);
        }

        return $result;
    }

    /**
     * Implementing models can override this method to set additional query
     * parameters to be used when searching
     *
     * @param  EloquentBuilder $query
     * @return EloquentBuilder
     */
    public function setKeysForSearch(EloquentBuilder $query): EloquentBuilder
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
            $query = $model->setKeysForSaveQuery($this->newQueryWithoutScopes());

            $this->setKeysForSearch($query);

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
            $query = $model->setKeysForSaveQuery($this->newQueryWithoutScopes());

            $this->setKeysForSearch($query);

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

        $array['id'] = $this->id;

        unset($array['_id']);

        foreach ((array) $this->indexAsChildDocuments as $field) {
            $subDocuments = $this->$field ?? [];

            foreach ($subDocuments as $subDocument) {
                $array['child_documents'][] = $this->getSubDocumentIndexData($subDocument, $field);
            }
        }

        $array = $this->datesToSearchable($array);

        return $array;
    }

    /**
     * Convert all dates to searchable format
     *
     * @param  array $array
     * @return array
     */
    public function datesToSearchable(array $array): array
    {
        foreach ($this->getDates() as $dateField) {
            if (isset($array[$dateField])) {
                $array[$dateField] = $this->fromDateTimeSearchable($array[$dateField]);
            }
        }

        foreach ($this->getArrayableRelations() as $key => $value) {
            $attributeName = snake_case($key);

            if (isset($array[$attributeName]) && method_exists($value, 'toSearchableArray')) {
                $array[$attributeName] = $value->datesToSearchable($array[$attributeName]);
            } elseif (isset($array[$attributeName]) && $value instanceof \Illuminate\Support\Collection) {
                $array[$attributeName] = $value->map(function ($item, $i) use ($array, $attributeName) {
                    if (method_exists($item, 'toSearchableArray')) {
                        return $item->datesToSearchable($array[$attributeName][$i]);
                    }

                    return $item;
                })->all();
            }
        }

        return $array;
    }

    /**
     * Convert a DateTime to a string in ES format.
     *
     * @param  \DateTime|int $value
     * @return string
     */
    public function fromDateTimeSearchable($value): string
    {
        return empty($value) ? $value : $this->asDateTime($value)->format($this->getSearchableDateFormat());
    }

    /**
     * Return the format to be used for dates in Elasticsearch
     *
     * @return string
     */
    public function getSearchableDateFormat(): string
    {
        return 'Y-m-d\TH:i:s';
    }

    /**
     * Build index details for a sub document
     *
     * @param  Model $document
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
