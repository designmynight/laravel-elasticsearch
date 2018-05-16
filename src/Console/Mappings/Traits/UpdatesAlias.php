<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings\Traits;

use DesignMyNight\Elasticsearch\Console\Mappings\Exceptions\FailedToUpdateAlias;

/**
 * Trait UpdatesAlias
 *
 * @property \GuzzleHttp\Client client
 * @property string             host
 * @package DesignMyNight\Elasticsearch\Console\Mappings\Traits
 */
trait UpdatesAlias
{

    /**
     * @param string $alias
     *
     * @return string
     */
    protected function getActiveIndex(string $alias):string
    {
        try {
            $body = $this->client->get("{$this->host}/{$alias}/_alias/*")->getBody();

            return array_keys(json_decode($body))[0];
        }
        catch (\Exception $exception) {
            $this->error('Failed to retrieve the current active index.');
        }

        return '';
    }

    /**
     * @param string $mapping
     *
     * @return string
     */
    protected function getAlias(string $mapping):string
    {
        return preg_replace('/[0-9_]+/', '', $mapping, 1);
    }

    /**
     * @param string      $index
     * @param string|null $alias
     * @param string|null $oldIndex
     *
     * @return bool
     */
    protected function updateAlias(string $index, string $alias = null, string $oldIndex = null):bool
    {
        $this->info("Updating alias for mapping: {$index}");

        $alias = $alias ?? $this->getAlias($index);

        $body = [
            'actions' => [
                [
                    'remove' => [
                        'index' => $oldIndex ?? $this->getActiveIndex($alias),
                        'alias' => $alias
                    ],
                    'add'    => [
                        'index' => $index,
                        'alias' => $alias
                    ]
                ]
            ]
        ];

        try {
            $body = $this->client->post("{$this->host}/_aliases", [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode($body)
            ])->getBody();
            $body = json_decode($body);

            if (isset($body['error'])) {
                throw new FailedToUpdateAlias($body['error']['reason'], $body['status']);
            }
        }
        catch (\Exception $exception) {
            $this->error("Failed to update alias: {$index}\n\n{$exception->getMessage()}");

            return false;
        }

        $this->info("Updated alias for mapping: {$index}");

        return true;
    }
}