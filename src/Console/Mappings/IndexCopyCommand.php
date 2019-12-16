<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Support\ElasticsearchException;
use Elasticsearch\Common\Exceptions\ElasticsearchException as ElasticsearchExceptionInterface;
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

        $connection = DB::connection('elasticsearch');

        $body = [
            'source' => [
                'index' => $fromIndex,
            ],
            'dest' => [
                'index' => $toIndex,
            ],
        ];

        try {
            $this->output->write("Copying ${fromIndex} to ${toIndex}...", true);

            $result = $connection->reindex([
                'body' => json_encode($body),
            ]);
        } catch (ElasticsearchExceptionInterface $e) {
            $e = new ElasticsearchException($e);

            $this->output->error((string) $e);

            return;
        }

        $this->reportResult($result);
    }

    /**
     * @param $result
     */
    private function reportResult($result): void
    {
        // report any failures
        if ($result['failures']) {
            $this->output->warning('Failures');
            $this->output->table(array_keys($result['failures'][0]), $result['failures']);
        }

        // format results in strings
        $result['timed_out'] = $result['timed_out'] ? 'true' : 'false';
        $result['failures'] = count($result['failures']);

        unset($result['retries']);

        // report success
        $this->output->success('Copy complete, see results below');
        $this->output->table(array_keys($result), [$result]);
    }
}
