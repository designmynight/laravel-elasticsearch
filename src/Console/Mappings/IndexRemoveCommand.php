<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Elasticsearch\ClientBuilder;

/**
 * Class IndexRemoveCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexRemoveCommand extends Command
{

    /** @var string $description */
    protected $description = 'Remove index from Elasticsearch';

    /** @var string $signature */
    protected $signature = 'index:remove {index? : Name of the index to remove.}';

    /**
     * IndexRemoveCommand constructor.
     *
     * @param ClientBuilder $client
     */
    public function __construct(ClientBuilder $client)
    {
        parent::__construct($client);
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $index = $this->argument('index');

        if (!$index) {
            $indices = collect($this->client->cat()->indices())->sortBy('index')->pluck('index')->toArray();
            $index = $this->choice('Which index would you like to delete?', $indices);
        }

        if (!$this->confirm("Are you sure you wish to remove the index {$index}?")) {
            return;
        }

        $this->removeIndex($index);
    }

    /**
     * @param string $index
     *
     * @return bool
     */
    protected function removeIndex(string $index):bool
    {
        $this->info("Removing index: {$index}");

        try {
            $this->client->indices()->delete(['index' => $index]);
        }
        catch (\Exception $exception) {
            $message = json_decode($exception->getMessage(), true);
            $this->error("Failed to remove index: {$index}. Reason: {$message['error']['root_cause'][0]['reason']}");

            return false;
        }

        $this->info("Removed index: {$index}");

        return true;
    }
}