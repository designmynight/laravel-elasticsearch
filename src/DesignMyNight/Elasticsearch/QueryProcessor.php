<?php

namespace DesignMyNight\Elasticsearch;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Processors\Processor as BaseProcessor;

class QueryProcessor extends BaseProcessor
{

    protected $rawResponse;

    protected $aggregations;

    /**
     * Process the results of a "select" query.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $results
     * @return array
     */
    public function processSelect(Builder $query, $results)
    {
        $this->rawResponse = $results;

        $this->aggregations = $results['aggregations'] ?? [];

        // Return a generator if we got a scroll cursor in the results
        if (isset($results['_scroll_id'])){
            return $this->yieldResults($results);
        }
        else {
            $documents = [];

            foreach ($results['hits']['hits'] as $result) {
                $documents[] = $this->documentFromResult($result);
            }

            return $documents;
        }
    }

    protected function yieldResults($results)
    {
        // First yield each result from the initial request
        foreach ($results['hits']['hits'] as $result){
            yield $this->documentFromResult($result);
        }

        // Then go through the scroll cursor getting one result at a time
        if (isset($results['scrollCursor'])){
            foreach ($results['scrollCursor'] as $result){
                // TODO: add _id to result
                $document = $this->documentFromResult($result);
                yield $document;
            }
        }
    }

    protected function documentFromResult($result)
    {
        $document = $result['_source'];
        $document['_id'] = $result['_id'];

        return $document;
    }

    public function getRawResponse()
    {
        return $this->rawResponse;
    }

    public function getAggregationResults()
    {
        return $this->aggregations;
    }
}
