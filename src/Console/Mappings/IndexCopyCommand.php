<?php

namespace DesignMyNight\Elasticsearch\Console\Mappings;

use DesignMyNight\Elasticsearch\Console\Mappings\Traits\GetsIndices;
use DesignMyNight\Elasticsearch\Support\ElasticsearchException;
use Elasticsearch\Common\Exceptions\ElasticsearchException as ElasticsearchExceptionInterface;

/**
 * Class IndexCopyCommand
 *
 * @package DesignMyNight\Elasticsearch\Console\Mappings
 */
class IndexCopyCommand extends Command
{
    use GetsIndices;

    /**
     * @var string $description
     */
    protected $description = 'Populate an index with all documents from another index';

    /**
     * @var string $signature
     */
    protected $signature = 'index:copy {from?} {to?}';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $from = $this->from();
        $to = $this->to();

        $body = [
            'source' => ['index' => $from],
            'dest' => ['index' => $to],
        ];

        if ($this->confirm("Would you like to copy {$from} to {$to}?")) {
            try {
                $this->report(
                    $this->client->reindex(['body' => json_encode($body)])
                );
            } catch (ElasticsearchExceptionInterface $exception) {
                $exception = new ElasticsearchException($exception);

                $this->output->error((string) $exception);
            }
        }
    }

    /**
     * @return string
     */
    protected function from(): string
    {
        if ($from = $this->argument('from')) {
            return $from;
        }

        return $this->choice(
            'Which index would you like to copy from?',
            collect($this->indices())->pluck('index')->toArray()
        );
    }

    /**
     * @return string
     */
    protected function to(): string
    {
        if ($to = $this->argument('to')) {
            return $to;
        }

        return $this->choice(
            'Which index would you like to copy to?',
            collect($this->indices())->pluck('index')->toArray()
        );
    }

    /**
     * @param array $result
     */
    private function report(array $result): void
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
