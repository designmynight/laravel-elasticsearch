<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

/**
 * Class IndexUpdateCommand
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexUpdateCommand extends Command
{

    /** @var string $description */
    protected $description = 'Updates an exiting index mapping';

    /** @var string $signature */
    protected $signature = 'index:update {index: Name of index to update} {type: Name of document type to update}';

    /**
     * Executes the command.
     */
    public function handle()
    {
        ['index' => $index, 'type' => $type] = $this->options();

        $this->line("Updating document type $type on index $index");

        $params = [
            'index' => $index,
            'type'  => $type,
            'body'  => [

            ],
        ];

        try {
            $this->client->indices()->putMapping($params);

            $this->info('Successfully updated index.');
        }
        catch (\Exception $exception) {
            $this->error('Failed to update index.');
        }
    }
}