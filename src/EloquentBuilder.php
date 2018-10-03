<?php

namespace DesignMyNight\Elasticsearch;

use Generator;
use Illuminate\Database\Eloquent\Builder as BaseBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

class EloquentBuilder extends BaseBuilder
{
    protected $type;

    /**
     * Set a model instance for the model being queried.
     *
     * @param Model $model
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;

        $this->query->from($model->getSearchIndex());

        $this->query->type($model->getSearchType());

        return $this;
    }

    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Collection|static[]
     */
    public function get($columns = ['*'])
    {
        $builder = $this->applyScopes();

        $models = $builder->getModels($columns);

        // If we got a generator response then we'll return it without eager loading
        if ($models instanceof Generator){
            // Throw an exception if relations were supposed to be eager loaded
            if ($this->eagerLoad){
                throw new Exception('Eager loading relations is not currently supported with Generator responses from a scroll search');
            }

            return $models;
        }
        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        return $builder->getModel()->newCollection($models);
    }

    public function getAggregations(string $collectionClass = ''): Collection
    {
        $collectionClass = $collectionClass ?: Collection::class;
        $aggregations = $this->query->getAggregationResults();

        return new $collectionClass($aggregations);
    }

    /**
     * Get the hydrated models without eager loading.
     *
     * @param  array  $columns
     * @return \Illuminate\Database\Eloquent\Model[]|Generator
     */
    public function getModels($columns = ['*'])
    {
        $results = $this->query->get($columns);

        if ($results instanceof Generator){
            return $this->yieldResults($results);
        }

        return $this->model->hydrate(
            $results->all()
        )->all();
    }

    /**
     * @inheritdoc
     */
    public function hydrate(array $items)
    {
        $instance = $this->newModelInstance();

        return $instance->newCollection(array_map(function ($item) use ($instance) {
            return $instance->newFromBuilder($item, $this->getConnection()->getName());
        }, $items));
    }

    /**
     * Get a generator for the given query.
     *
     * @return Generator
     */
    protected function yieldResults($results)
    {
        $instance = $this->newModelInstance();

        foreach ( $results as $result ){
            yield $instance->newFromBuilder($result);
        }
    }

    /**
     * Paginate the given query.
     *
     * @param  int  $perPage
     * @param  array  $columns
     * @param  string  $pageName
     * @param  int|null  $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        $results = $this->forPage($page, $perPage)->get($columns);

        $total = $this->toBase()->getCountForPagination();

        return new LengthAwarePaginator($results, $total, $perPage, $page, [
            'path'     => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }
}
