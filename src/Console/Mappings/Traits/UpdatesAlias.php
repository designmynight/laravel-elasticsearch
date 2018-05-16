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

            return array_keys(json_decode($body, true))[0];
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
     * @param string|null $currentIndex
     * @param bool        $removeOldIndex
     */
    protected function updateAlias(string $index, string $alias = null, string $currentIndex = null, bool $removeOldIndex = false):void
    {
        $this->info("Updating alias for mapping: {$index}");

        $alias = $alias ?? $this->getAlias($index);
        $currentIndex = $currentIndex ?? $this->getActiveIndex($alias);

        $body = [
            'actions' => [
                [
                    'remove' => [
                        'index' => $currentIndex,
                        'alias' => $alias
                    ],
                ],
                [
                    'add' => [
                        'index' => $index,
                        'alias' => $alias
                    ]
                ]
            ]
        ];

        try {
            $responseBody = $this->client->post("{$this->host}/_aliases", [
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
                'body'    => json_encode($body)
            ])->getBody();
            $responseBody = json_decode($responseBody, true);

            if (isset($responseBody['error'])) {
                throw new FailedToUpdateAlias($responseBody['error']['reason'], $responseBody['status']);
            }
        }
        catch (\Exception $exception) {
            $this->error("Failed to update alias: {$alias}\n\n{$exception->getMessage()}");

            return;
        }

        $this->info("Updated alias for mapping: {$index}");

        if ($removeOldIndex) {
            $this->call('index:remove', [
                'index' => $currentIndex
            ]);
        }
    }
}