<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings\Traits;

use Elasticsearch\Client;

/**
 * Trait UpdatesAlias
 *
 * @property Client client
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
            $aliases = collect($this->client->cat()->aliases());
        } catch (\Exception $exception) {
            $this->error('Failed to retrieve the current active index.');
        }

        $aliases = $aliases->filter(function (array $item) use ($alias):bool {
            return str_contains($item['index'], $alias);
        })->sortByDesc('index');

        if ($aliases->count() === 1) {
            return $aliases->first()['index'];
        }

        $index = $this->choice('Which index is the current index?', $aliases->pluck('index')->toArray(), 0);

        return $aliases->firstWhere('index', $index)['index'];
    }

    /**
     * Change 2018_09_04_104700_update_pages_dev to pages_dev.
     *
     * @param string $mapping
     *
     * @return string
     */
    protected function getAlias(string $mapping):string
    {
        return preg_replace('/^\d{4}\_\d{2}\_\d{2}\_\d{6}\_(update_)?/', '', $mapping, 1);
    }

    /**
     * @param string $alias
     *
     * @return string
     */
    protected function getIndex(string $alias):string
    {
        try {
            $indices = collect($this->client->cat()->indices());
        } catch (\Exception $exception) {
            $this->error('An error occurred attempting to retrieve indices.');
        }

        $relevant = $indices->filter(function (array $item) use ($alias):bool {
            return str_contains($item['index'], $alias);
        })->sortByDesc('index');

        return $this->choice('Which index would you like to use?', $relevant->pluck('index')->toArray(), 0);
    }

    /**
     * @param string|null $index
     * @param string|null $alias
     * @param string|null $currentIndex
     * @param bool        $removeOldIndex
     */
    protected function updateAlias(?string $index, string $alias = null, ?string $currentIndex = null, bool $removeOldIndex = false):void
    {
        $index = $index ?? $this->getIndex($alias);

        $this->line("Updating alias to mapping: {$index}");

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
            $this->client->indices()->updateAliases(['body' => $body]);
        } catch (\Exception $exception) {
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
