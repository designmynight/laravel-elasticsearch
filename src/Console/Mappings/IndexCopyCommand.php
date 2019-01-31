<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use Illuminate\Support\Facades\DB;

/**
 * Class IndexCopyCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexCopyCommand extends Command
{
    /**
     * @var string $description
     */
    protected $description = 'Populate an index with all documents from another index';

    /**
     * @var string $signature
     */
    protected $signature = 'index:copy {from} {to}';

    /**
     * @var int
     */
    protected $batchSize = 1000;

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        ['from' => $fromIndex, 'to' => $toIndex] = $this->arguments();

        $bulkParams = ['body' => []];
        $connection = DB::connection('elasticsearch');

        $query = [
            'index' => $fromIndex,
            'size' => 0,
            'body' => [
                'query' => [
                    'match_all' => (object) []
                ],
            ],
        ];

        $numResults = $connection->select($query)['hits']['total'];
        $bar = $this->output->createProgressBar($numResults);
        $bar->start();

        foreach ($connection->cursor($query) as $i => $doc) {
            $indexParams = [
                'index' => [
                    '_index' => $toIndex,
                    '_type' => $doc['_type'],
                    '_id' => $doc['_id'],
                ],
            ];

            if (isset($doc['_parent'])) {
                $indexParams['index']['parent'] = $doc['_parent'];
            }

            $bulkParams['body'][] = $indexParams;
            $bulkParams['body'][] = $doc['_source'];

            if (($i + 1) % $this->batchSize === 0) {
                $responses = $connection->insert($bulkParams);
                $bulkParams = ['body' => []];

                $bar->advance($this->batchSize);
            }
        }

        if (! empty($bulkParams['body'])) {
            $responses = $connection->insert($bulkParams);
        }

        $bar->finish();
    }
}
