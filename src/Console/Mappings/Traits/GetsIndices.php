<?php
/**
 * Created by PhpStorm.
 * User: james.osullivan
 * Date: 03/08/2018
 * Time: 14:29
 */

namespace DesignMyNight\Elasticsearch\Console\Mappings\Traits;

trait GetsIndices
{
    /**
     * @return array
     */
    protected function getIndices():array
    {
        try {
            return collect($this->client->cat()->indices())->sortBy('index')->toArray();
        }
        catch (\Exception $exception) {
            $this->error('Failed to retrieve indices.');
        }

        return [];
    }
}