<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings\Traits;

use DesignMyNight\Elasticsearch\Console\Mappings\Exceptions\FailedToUpdateAlias;
use Elasticsearch\ClientBuilder;

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
            $aliases = collect(ClientBuilder::create()->build()->cat()->aliases());
            $aliases = $aliases->filter(function (array $item) use ($alias):bool {
                return str_contains($item['index'], $alias);
            })->sortByDesc('index');

            $index = $this->choice('Which index is the current index?', $aliases->pluck('index')->toArray(), 0);

            return $aliases->firstWhere('index', $index)['index'];
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
    protected function updateAlias(string $index, string $alias = null, ?string $currentIndex = null, bool $removeOldIndex = false):void
    {
        $this->info("Updating alias with mapping: {$index}");

        $alias = $alias ?? $this->getAlias($index);
        $currentIndex = $currentIndex ?? $this->getActiveIndex($alias);

        $body = [
            'actions' => [
                [
                    'remove' => [
                        'index' => $currentIndex,
                        'alias' => $alias,
                    ],
                ],
                [
                    'add' => [
                        'index' => $index,
                        'alias' => $alias,
                    ],
                ],
            ],
        ];

        try {
            ClientBuilder::create()->build()->indices()->updateAliases(['body' => $body]);
        }
        catch (\Exception $exception) {
            $this->error("Failed to update alias: {$alias}. {$exception->getMessage()}");

            return;
        }

        $this->info("Updated alias to mapping: {$index}");

        if ($removeOldIndex) {
            $this->call('index:remove', [
                'index' => $currentIndex,
            ]);
        }
    }
}