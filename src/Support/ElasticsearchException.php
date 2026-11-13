<?php

namespace DesignMyNight\Elasticsearch\Support;

use OpenSearch\Exception\OpenSearchExceptionInterface as BaseElasticsearchException;
use Exception;

class ElasticsearchException extends Exception
{
    /** @var array */
    private $raw = [];

    /**
     * ElasticsearchException constructor.
     *
     * @param BaseElasticsearchException $exception
     */
    public function __construct(BaseElasticsearchException $exception)
    {
        $this->parseException($exception);
    }

    /**
     * @return array
     */
    public function getRaw(): array
    {
        return $this->raw;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return "{$this->getCode()}: {$this->getMessage()}";
    }

    /**
     * @param BaseElasticsearchException $exception
     */
    private function parseException(BaseElasticsearchException $exception): void
    {
        $body = json_decode($exception->getMessage(), true);

        if (!is_array($body)) {
            [$type, $reason] = array_pad(explode(': ', $exception->getMessage(), 2), 2, null);
            $body = ['error' => ['type' => $type, 'reason' => $reason ?? $type]];
        }

        $this->message = $body['error']['reason'];
        $this->code = $body['error']['type'];

        $this->raw = $body;
    }
}
