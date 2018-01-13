<?php

namespace DesignMyNight\Elasticsearch;

use Generator;
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
     * @return array|Generator
     */
    public function processSelect(Builder $query, $results)
    {
        $this->rawResponse = $results;

        $this->aggregations = $results['aggregations'] ?? [];

        $this->query = $query;

        // Return a generator if we got a scroll cursor in the results
        if (isset($results['_scroll_id'])){
            return $this->yieldResults($results);
        }
        else {
            $documents = [];

            foreach ($results['hits']['hits'] as $result) {
                $documents[] = $this->documentFromResult($query, $result);
            }

            return $documents;
        }
    }

    /**
     * Go through the results of a scroll search, yielding one result at a time
     *
     * @param array $results
     * @return Generator
     */
    protected function yieldResults($results): Generator
    {
        // First yield each result from the initial request
        foreach ($results['hits']['hits'] as $result){
            yield $this->documentFromResult($this->query, $result);
        }

        // Then go through the scroll cursor getting one result at a time
        if (isset($results['scrollCursor'])){
            foreach ($results['scrollCursor'] as $result){
                $document = $this->documentFromResult($this->query, $result);

                yield $document;
            }
        }
    }

    /**
     * Create a document from the given result
     *
     * @param  Builder $query
     * @param  array $result
     * @return array
     */
    protected function documentFromResult(Builder $query, array $result): array
    {
        $document = $result['_source'];
        $document['_id'] = $result['_id'];

        if ($query->includeInnerHits && isset($result['inner_hits'])){
            $document = $this->addInnerHitsToDocument($document, $result['inner_hits']);
        }

        return $document;
    }

    /**
     * Add inner hits to a document
     *
     * @param  array $document
     * @param  array $innerHits
     * @return array
     */
    protected function addInnerHitsToDocument($document, $innerHits): array
    {
        foreach ($innerHits as $documentType => $hitResults){
            foreach ( $hitResults['hits']['hits'] as $result ){
                $document['inner_hits'][$documentType][] = array_merge(['_id' => $result['_id']], $result['_source']);
            }
        }

        return $document;
    }

    /**
     * Get the raw Elasticsearch response
     *
     * @param array
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    /**
     * Get the raw aggregation results
     *
     * @param array
     */
    public function getAggregationResults(): array
    {
        return $this->aggregations;
    }
}
